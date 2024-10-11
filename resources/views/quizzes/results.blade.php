<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Wyniki testu: ' . $quiz->title) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <table class="table-auto w-full">
                        <thead>
                            <tr>
                                <th class="border px-4 py-2">Numer pytania</th>
                                <th class="border px-4 py-2">Twoja odpowiedź</th>
                                <th class="border px-4 py-2">Wynik</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $index => $result)
                                <tr>
                                    <td class="border px-4 py-2">{{ $index + 1 }}</td> <!-- Numer pytania -->
                                    <td class="border px-4 py-2">
                                        @if($result['user_answer'])
                                            <pre>{{ $result['user_answer'] }}</pre>
                                        @else
                                            {{ $result['user_option'] }}
                                        @endif
                                    </td>
                                    <td class="border px-4 py-2">
                                        @if($result['is_correct'])
                                            <span class="text-green-500">Poprawne</span>
                                        @else
                                            <span class="text-red-500">Błędne</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        <!-- Przycisk Powrót do dashboardu -->
                        <a href="{{ route('user.dashboard') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Powrót do panelu użytkownika
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
