<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Stwórz Grupę') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Tworzenie Nowej Grupy</h1>

                    <!-- Formularz Tworzenia Grupy -->
                    <form action="{{ route('groups.store') }}" method="POST">
                        @csrf
                        
                        <!-- Nazwa grupy -->
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Nazwa Grupy:</label>
                            <input type="text" name="name" id="name" class="w-full p-2 border border-gray-300 rounded-md" required>
                        </div>

                        <!-- Opis grupy -->
                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700">Opis:</label>
                            <textarea name="description" id="description" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                        </div>

                        <!-- Przycisk zatwierdzenia -->
                        <div>
                            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Stwórz Grupę
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
