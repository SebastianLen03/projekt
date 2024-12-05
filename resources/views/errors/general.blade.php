<x-app-layout>
    <!-- Sekcja nagłówka strony -->
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Wystąpił błąd
        </h2>
    </x-slot>

    <!-- Główna zawartość strony -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Nagłówek z komunikatem o błędzie -->
                    <h1 class="text-2xl font-bold mb-6">Wystąpił błąd</h1>

                    <!-- Wyświetlenie przekazanego komunikatu błędu -->
                    <p class="text-gray-700 mb-4">
                        {{ $message ?? 'Przepraszamy, wystąpił nieoczekiwany błąd. Spróbuj ponownie później.' }}
                    </p>

                    <!-- Przycisk powrotu do strony głównej -->
                    <a href="{{ url('/') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Powrót do strony głównej
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
