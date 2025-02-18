<x-app-layout>
    <div class="py-12 lg:px-16">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Dane nowej grupy</h2>

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
            <div class="text-right">
                <x-dark-button type="submit">
                    Stwórz Grupę
                </x-dark-button>
            </div>
        </form>
    </div>
</x-app-layout>
