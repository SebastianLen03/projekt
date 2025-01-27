<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\UserAttempt;
use App\Models\QuizVersion;
use App\Models\UserAnswer;
use App\Models\VersionedAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizVersionController extends Controller
{
    public function showVersion($quizId, $versionId)
    {
        $quiz = Quiz::findOrFail($quizId);
    
        $version = QuizVersion::where('quiz_id', $quiz->id)
            ->where('id', $versionId)
            ->with(['versionedQuestions.answers'])
            ->firstOrFail();
    
        // Podejścia
        $userAttempts = UserAttempt::where('quiz_id',$quiz->id)
            ->where('quiz_version_id',$version->id)
            ->with(['user','quizVersion'])
            ->get();
    
        // Pogrupowane odpowiedzi
        $attemptIds = $userAttempts->pluck('id');
        $allAnswers = UserAnswer::whereIn('attempt_id',$attemptIds)->get();
    
        /**
         * 1) Rozkład wyników w %  ($scoreDistData)
         *    Przykład z przedziałami: 0-20%, 20-40%, 40-60%, 60-80%, 80-100%
         */
        $scoreDistData = [
            '0-20%'  => 0,
            '20-40%' => 0,
            '40-60%' => 0,
            '60-80%' => 0,
            '80-100%'=> 0,
        ];
        // policz totalPossiblePoints
        $versionQuestions = $version->versionedQuestions;
        $totalPoints = 0;
        foreach($versionQuestions as $q){
            $totalPoints += $q->points;
        }
        foreach($userAttempts as $att){
            if($totalPoints>0){
                $scorePerc = ($att->score / $totalPoints)*100;
                if($scorePerc<20)      $scoreDistData['0-20%']++;
                elseif($scorePerc<40)  $scoreDistData['20-40%']++;
                elseif($scorePerc<60)  $scoreDistData['40-60%']++;
                elseif($scorePerc<80)  $scoreDistData['60-80%']++;
                else                   $scoreDistData['80-100%']++;
            }
        }
    
        /**
         * 2) Najczęściej popełniane błędy => $commonMistakesData
         *    Dotychczas obliczaliśmy error_rate = wrongCount/allCount
         *    ALE zamiast question_text => "Pytanie i"
         */
        $commonMistakesData = [];
        // Licznik, by móc nazwać "Pytanie 1", "Pytanie 2", ...
        $index = 1;
        foreach($versionQuestions as $q){
            $wrong=0; $all=0;
            foreach($userAttempts as $att){
                $uAnswers = $allAnswers->where('attempt_id',$att->id);
                $ua = $uAnswers->firstWhere('versioned_question_id',$q->id);
                if($ua){
                    $all++;
                    if(!$ua->is_correct) $wrong++;
                }
            }
            if($all>0){
                $errorRate = $wrong/$all;
                $commonMistakesData[] = [
                    // tu "Pytanie 1", "Pytanie 2" itd.
                    'question_label'=> "Pytanie $index",
                    'error_rate'    => $errorRate,
                ];
            }
            $index++;
        }
        // sort malejąco
        usort($commonMistakesData, fn($a,$b)=>$b['error_rate']<=>$a['error_rate']);
    
        /**
         * 3) Najpopularniejsze błędne odpowiedzi => $wrongAnswersData
         *    Ustalamy % błędnych odpowiedzi dla każdego "pytania i".
         *    Sort malejąco
         */
        $wrongAnswersData = [];
        $index = 1;
        foreach($versionQuestions as $q){
            $all=0; $wrong=0;
            // Ile userAnswers w ogóle do tego pytania?
            foreach($userAttempts as $att){
                $uAnswers = $allAnswers->where('attempt_id',$att->id);
                $ua = $uAnswers->firstWhere('versioned_question_id',$q->id);
                if($ua){
                    $all++;
                    if(!$ua->is_correct) $wrong++;
                }
            }
            if($all>0){
                $pct = $wrong/$all * 100;
                $wrongAnswersData[] = [
                    'question_label'=>"Pytanie $index",
                    'percent_wrong'=>$pct,
                ];
            }
            $index++;
        }
        // sort malejąco
        usort($wrongAnswersData, fn($a,$b)=>$b['percent_wrong']<=>$a['percent_wrong']);
    
    
        // Zwracamy do widoku
        return view('quizzes.show-version', [
            'quiz'               => $quiz,
            'version'            => $version,
            'userAttempts'       => $userAttempts,
            // Grupowanie odp. do sekcji "podejścia"
            'groupedUserAnswers' => $allAnswers->groupBy('attempt_id'),
    
            // DANE DO WYKRESÓW:
            'scoreDistData'      => $scoreDistData,
            // usuwamy "scoreOverTimeDataOld" całkowicie, bo wykres "Wyniki w czasie" niepotrzebny
            'commonMistakesData' => $commonMistakesData,
            'wrongAnswersData'   => $wrongAnswersData,
            // ewentualnie: userRankingData, timeVsScoreData, ...
            'userRankingData'    => $this->buildUserRanking($userAttempts, $versionQuestions),
            'timeVsScoreData'    => $this->buildTimeVsScore($userAttempts),
        ]);
    }
    
    // Pozostałe metody: activateVersion, deactivateVersion, deleteVersion, renameVersion

    public function activateVersion(Request $request, $quizId, $versionId)
    {
        $user = Auth::user();
        $quiz = Quiz::where('id', $quizId)->where('user_id', $user->id)->firstOrFail();

        $versionToActivate = QuizVersion::where('id', $versionId)
            ->where('quiz_id', $quiz->id)
            ->where('is_draft', false)
            ->firstOrFail();

        // Czy inna wersja jest aktywna?
        $alreadyActive = QuizVersion::where('quiz_id', $quiz->id)
            ->where('is_draft', false)
            ->where('is_active', true)
            ->where('id', '<>', $versionId)
            ->exists();

        if ($alreadyActive) {
            return back()->withErrors([
                'error' => 'Występuje już inna aktywna wersja. Dezaktywuj ją najpierw.'
            ]);
        }

        // Aktywujemy wybraną
        $versionToActivate->is_active = true;
        $versionToActivate->save();

        // Ustawiamy quiz->is_active
        $quiz->is_active = true;
        $quiz->save();

        return back()->with('message', 'Wersja „'.$versionToActivate->version_name.'” została aktywowana.');
    }

    public function deactivateVersion(Request $request, $quizId, $versionId)
    {
        $user = Auth::user();
        $quiz = Quiz::where('id', $quizId)->where('user_id', $user->id)->firstOrFail();

        $versionToDeactivate = QuizVersion::where('id', $versionId)
            ->where('quiz_id', $quiz->id)
            ->where('is_draft', false)
            ->where('is_active', true)
            ->firstOrFail();

        $versionToDeactivate->is_active = false;
        $versionToDeactivate->save();

        // Sprawdzamy czy inne wersje są aktywne
        $anyActive = $quiz->quizVersions()
            ->where('is_draft', false)
            ->where('is_active', true)
            ->exists();

        if (!$anyActive) {
            $quiz->is_active = false;
            $quiz->save();
        }

        return back()->with('message', 'Wersja „'.$versionToDeactivate->version_name.'” została dezaktywowana.');
    }

    public function deleteVersion($quizId, $versionId)
    {
        $user = Auth::user();
        $quiz = Quiz::where('id', $quizId)->where('user_id', $user->id)->firstOrFail();

        $version = QuizVersion::where('id', $versionId)
            ->where('quiz_id', $quiz->id)
            ->where('is_draft', false)
            ->firstOrFail();

        if ($version->is_active) {
            return back()->withErrors([
                'error' => 'Nie można usunąć aktywnej wersji. Deaktywuj ją najpierw.'
            ]);
        }

        // Usuwamy pytania i odpowiedzi
        foreach ($version->versionedQuestions as $q) {
            $q->versionedAnswers()->delete();
            $q->delete();
        }
        $version->delete();

        return back()->with('message', "Wersja {$version->version_name} została usunięta.");
    }

    public function renameVersion(Request $request, $quizId, $versionId)
    {
        $request->validate([
            'version_name' => 'required|string|max:255',
        ]);

        $quiz = Quiz::where('id', $quizId)->where('user_id', Auth::id())->firstOrFail();

        $version = QuizVersion::where('id', $versionId)
            ->where('quiz_id', $quiz->id)
            ->where('is_draft', false)
            ->firstOrFail();

        $version->version_name = $request->input('version_name');
        $version->save();

        return back()->with('message', 'Nazwa wersji została zmieniona.');
    }

    /**
     * (12) Porównanie różnych wersji quizu
     */
    public function compareVersions($quizId)
    {
        $quiz = Quiz::findOrFail($quizId);

        // Pobierz wszystkie finalne wersje (is_draft=0)
        $versions = $quiz->quizVersions()
            ->where('is_draft', false)
            ->orderBy('version_number')
            ->get();

        // Tablica do widoku
        $versionsStats = [];
        foreach($versions as $v){
            $attempts = UserAttempt::where('quiz_id',$quiz->id)
                ->where('quiz_version_id',$v->id)
                ->get();

            if($attempts->count()===0){
                $versionsStats[] = [
                    'version_number'=>$v->version_number,
                    'version_name'=>$v->version_name ?? '',
                    'avg_score'=>0,
                    'pass_rate'=>0,
                    'avg_duration'=>0,
                ];
                continue;
            }

            // Średni wynik
            $avg_score = $attempts->avg('score');

            // Procent zdawalności => (passedCount / total) * 100 (musisz mieć logikę)
            $passedCount = 0;
            $totalPoints=0;
            $versionQuestions = $v->versionedQuestions;
            foreach($versionQuestions as $vq){
                $totalPoints+=$vq->points;
            }
            foreach($attempts as $att){
                $scorePerc=($totalPoints>0)?($att->score/$totalPoints)*100:0;
                $isPassed=false;
                if($v->has_passing_criteria){
                    if($v->passing_score && $att->score>=$v->passing_score) $isPassed=true;
                    elseif($v->passing_percentage && $scorePerc>=$v->passing_percentage) $isPassed=true;
                }
                if($isPassed) $passedCount++;
            }
            $pass_rate=($attempts->count()>0)?($passedCount/$attempts->count())*100:0;

            // Średni czas
            $sumSec=0; $countDur=0;
            foreach($attempts as $att){
                if($att->started_at && $att->ended_at){
                    $sec=max($att->ended_at->diffInSeconds($att->started_at),0);
                    $sumSec+=$sec; $countDur++;
                }
            }
            $avg_duration=$countDur>0?($sumSec/$countDur):0;

            $versionsStats[]=[
                'version_number'=> $v->version_number,
                'version_name'  => $v->version_name ?? '',
                'avg_score'     => round($avg_score,2),
                'pass_rate'     => round($pass_rate,2),
                'avg_duration'  => round($avg_duration,1),
            ];
        }

        return view('quizzes.versions-compare', [
            'quiz'=>$quiz,
            'versionsStats'=>$versionsStats,
        ]);
    }
    private function buildUserRanking($userAttempts, $versionQuestions)
{
    // Przykład: sumujemy "score" z attempts po user_id
    // i wyciągamy top 5 userów
    $ranking = [];

    foreach ($userAttempts as $att) {
        $uid = $att->user_id;
        if (!isset($ranking[$uid])) {
            $ranking[$uid] = [
                'user_name'   => $att->user->name,
                'total_score' => 0,
            ];
        }
        $ranking[$uid]['total_score'] += $att->score;
    }

    // Posortuj
    usort($ranking, function($a,$b){
        return $b['total_score'] <=> $a['total_score'];
    });

    // Możesz ograniczyć do np. top 10
    $ranking = array_slice($ranking, 0, 10);

    return $ranking;
}

private function buildTimeVsScore($userAttempts)
{
    // Przykład: zwracamy tablicę [ [duration=>..., score=>...], ... ]
    // dla wykresu scatter (czas vs. wynik)
    $timeVsScore = [];

    foreach ($userAttempts as $att) {
        if ($att->started_at && $att->ended_at) {
            $sec = 0;
            if ($att->started_at && $att->ended_at) {
                // Zamiast diffInSeconds, użyj timestamp
                $sec = $att->ended_at->timestamp - $att->started_at->timestamp;
                $sec = max($sec, 0);
            }
            $timeVsScore[] = [
                'duration' => $sec,
                'score'    => $att->score,
            ];
        }
        else {
            // ewentualnie pomijamy
        }
    }

    return $timeVsScore;
}

}
