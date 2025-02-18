<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizVersion;
use App\Models\VersionedQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Traits\LoadsQuizTrait;

class QuizDraftController extends Controller
{
    use LoadsQuizTrait;

    public function saveAll(Request $request, $quizId)
    {
        Log::info('saveAll payload', $request->all());
        $user = Auth::user();
        $quiz = $this->loadUserQuiz($quizId);

        $validated = $request->validate([
            'title'                => 'required|string|max:255',
            'has_passing_criteria' => 'required|boolean',
            'passing_type'         => 'nullable|string|in:points,percentage',
            'has_time_limit'       => 'required|boolean',
            'passing_score'        => 'nullable|integer|min:1',
            'passing_percentage'   => 'nullable|integer|min:0|max:100',
            'time_limit'           => 'nullable|integer|min:1',

            'questions'                     => 'required|array',
            'questions.*.id'               => 'nullable|exists:versioned_questions,id',
            'questions.*.question_text'     => 'required|string',
            'questions.*.type'             => 'required|string|in:multiple_choice,single_choice,open',
            'questions.*.points'           => 'required|integer|min:1',
            'questions.*.points_type'      => 'nullable|string|in:full,partial',

            'questions.*.expected_code'     => 'nullable|string',
            'questions.*.language'          => 'nullable|string|in:php,java',

            'questions.*.answers'                  => 'nullable|array',
            'questions.*.answers.*.id'            => 'nullable|exists:versioned_answers,id',
            'questions.*.answers.*.text'          => 'required|string',
            'questions.*.answers.*.is_correct'    => 'required|boolean',
        ]);

        // Aktualizacja quizu
        $quiz->title = $validated['title'];
        $quiz->save();

        // Znajdujemy lub tworzymy wersję draft
        $draftVersion = $quiz->quizVersions()->where('is_draft', true)->first();
        if (!$draftVersion) {
            $latestFinal = $quiz->quizVersions()->where('is_draft', false)->max('version_number') ?: 0;
            $draftVersion = $quiz->quizVersions()->create([
                'version_number' => $latestFinal + 1,
                'is_draft'       => true,
                'is_active'      => false,
            ]);
        } else {
            $draftVersion->has_passing_criteria = $validated['has_passing_criteria'];
            if ($draftVersion->has_passing_criteria) {
                if ($request->input('passing_type') === 'points') {
                    $draftVersion->passing_score = $validated['passing_score'];
                    $draftVersion->passing_percentage = null;
                } elseif ($request->input('passing_type') === 'percentage') {
                    $draftVersion->passing_percentage = $validated['passing_percentage'];
                    $draftVersion->passing_score = null;
                }
            } else {
                $draftVersion->passing_score = null;
                $draftVersion->passing_percentage = null;
            }
            $draftVersion->time_limit = $validated['has_time_limit'] ? $validated['time_limit'] : null;
            $draftVersion->save();
        }

        // Obsługa pytań
        foreach ($validated['questions'] as $questionData) {
            if (isset($questionData['id'])) {
                $versionedQuestion = VersionedQuestion::find($questionData['id']);
                if ($versionedQuestion) {
                    $versionedQuestion->question_text = $questionData['question_text'];
                    $versionedQuestion->type = $questionData['type'];
                    $versionedQuestion->points = $questionData['points'];
                    $versionedQuestion->points_type = ($questionData['type'] === 'multiple_choice')
                        ? ($questionData['points_type'] ?? 'full')
                        : null;
                    $versionedQuestion->save();

                    // Usuwamy stare odpowiedzi
                    $versionedQuestion->versionedAnswers()->delete();

                    if ($questionData['type'] === 'open') {
                        $versionedQuestion->versionedAnswers()->create([
                            'expected_code' => $questionData['expected_code'] ?? null,
                            'language'      => $questionData['language'] ?? 'php',
                        ]);
                    } else {
                        if (!empty($questionData['answers'])) {
                            foreach ($questionData['answers'] as $ans) {
                                $versionedQuestion->versionedAnswers()->create([
                                    'text'       => $ans['text'],
                                    'is_correct' => $ans['is_correct'],
                                ]);
                            }
                        }
                    }
                }
            } else {
                $versionedQuestion = VersionedQuestion::create([
                    'quiz_version_id' => $draftVersion->id,
                    'question_text'   => $questionData['question_text'],
                    'type'            => $questionData['type'],
                    'points'          => $questionData['points'],
                    'points_type'     => ($questionData['type'] === 'multiple_choice')
                        ? ($questionData['points_type'] ?? 'full')
                        : null,
                ]);

                if ($questionData['type'] === 'open') {
                    $versionedQuestion->versionedAnswers()->create([
                        'expected_code' => $questionData['expected_code'] ?? null,
                        'language'      => $questionData['language'] ?? 'php',
                    ]);
                } else {
                    if (!empty($questionData['answers'])) {
                        foreach ($questionData['answers'] as $ans) {
                            $versionedQuestion->versionedAnswers()->create([
                                'text'       => $ans['text'],
                                'is_correct' => $ans['is_correct'],
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json(['message' => 'Quiz (draft) zapisany pomyślnie.']);
    }

    public function finalizeDraftVersion(Request $request, $quizId)
    {
        $user = Auth::user();
        $quiz = $this->loadUserQuiz($quizId);

        $draftVersion = $quiz->quizVersions()->where('is_draft', true)->firstOrFail();

        $request->validate([
            'version_name' => 'nullable|string|max:255'
        ]);

        $draftVersion->is_draft = false;
        $draftVersion->is_active = false;
        $draftVersion->version_name = $request->input('version_name') ?: ('Wersja ' . $draftVersion->version_number);
        $draftVersion->save();

        $newDraft = $draftVersion->replicate([
            'created_at', 'updated_at', 'is_draft', 'is_active'
        ]);
        $newDraft->is_draft = true;
        $newDraft->is_active = false;
        $newDraft->version_number = $draftVersion->version_number + 1;
        $newDraft->version_name = null;
        $newDraft->save();

        foreach ($draftVersion->versionedQuestions as $oldQ) {
            $newQ = $oldQ->replicate(['created_at','updated_at']);
            $newQ->quiz_version_id = $newDraft->id;
            $newQ->save();

            foreach ($oldQ->versionedAnswers as $oldA) {
                $newA = $oldA->replicate(['created_at','updated_at']);
                $newA->versioned_question_id = $newQ->id;
                $newA->save();
            }
        }

        return back()->with('message', 'Zapisano pełną wersję (nr '. $draftVersion->version_number .'). Utworzono nowy draft.');
    }

    public function updateAccess(Request $request, $quizId)
    {
        $quiz = $this->loadUserQuiz($quizId);

        $data = $request->validate([
            'multiple_attempts' => 'sometimes|boolean',
            'is_public'         => 'sometimes|boolean',
            'group_id'          => 'sometimes|integer|exists:groups,id'
        ]);

        if (array_key_exists('multiple_attempts', $data)) {
            $quiz->multiple_attempts = $data['multiple_attempts'];
            $quiz->save();
            return response()->json(['message' => 'Ustawienie wielokrotnych podejść zostało zmienione.']);
        }

        if (array_key_exists('is_public', $data)) {
            $quiz->is_public = $data['is_public'];
            $quiz->save();

            if ($quiz->is_public) {
                $quiz->groups()->detach();
            }

            return response()->json(['message' => 'Ustawienie publiczności quizu zostało zmienione.']);
        }

        if (array_key_exists('group_id', $data)) {
            $groupId = $data['group_id'];
            if ($quiz->groups->contains($groupId)) {
                $quiz->groups()->detach($groupId);
                return response()->json(['message' => 'Usunięto grupę z quizu.']);
            } else {
                $quiz->groups()->attach($groupId);
                return response()->json(['message' => 'Dodano grupę do quizu.']);
            }
        }

        return response()->json(['message' => 'Nie podano żadnych pól do zmiany.'], 400);
    }
}
