{{-- resources/views/quizzes/versions-compare.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Porównanie różnych wersji Quizu: {{ $quiz->title }}
        </h2>
    </x-slot>

    <!-- Załadowanie biblioteki Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="py-12 max-w-7xl mx-auto">
        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h1 class="text-2xl font-semibold mb-6">Porównanie wersji</h1>

            @if(empty($versionsStats) || count($versionsStats)===0)
                <p class="text-gray-600">Brak danych do porównania.</p>
            @else
                @php
                    // Wersje
                    $labels = array_map(fn($vs) => 'v'.$vs['version_number'].' ('.$vs['version_name'].')', $versionsStats);

                    // Wykres 1: Średni wynik
                    $avgScores = array_map(fn($vs) => $vs['avg_score'], $versionsStats);

                    // Wykres 2: Procent zdawalności
                    $passRates = array_map(fn($vs) => $vs['pass_rate'], $versionsStats);

                    // Wykres 3: Średni czas (w minutach)
                    $avgDurations = array_map(fn($vs) => round($vs['avg_duration']/60,1), $versionsStats); 
                @endphp

                <!-- W tabeli możesz też wyświetlić dane tekstowo -->
                <table class="min-w-full border-collapse mb-6">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2">Nr Wersji</th>
                            <th class="px-4 py-2">Średni Wynik</th>
                            <th class="px-4 py-2">% Zdawalności</th>
                            <th class="px-4 py-2">Średni Czas (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($versionsStats as $vs)
                        <tr class="border-b">
                            <td class="px-4 py-2">
                                v{{ $vs['version_number'] }} 
                                ({{ $vs['version_name'] }})
                            </td>
                            <td class="px-4 py-2">
                                {{ number_format($vs['avg_score'], 2) }}
                            </td>
                            <td class="px-4 py-2">
                                {{ number_format($vs['pass_rate'], 2) }}%
                            </td>
                            <td class="px-4 py-2">
                                {{ round($vs['avg_duration']/60,1) }} min
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <!-- Wykres 1: Średni wynik -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold mb-2">Średni wynik w wersjach</h3>
                    <canvas id="avgScoreChart"></canvas>
                </div>

                <!-- Wykres 2: % Zdawalności -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold mb-2">% Zdawalności w wersjach</h3>
                    <canvas id="passRateChart"></canvas>
                </div>

                <!-- Wykres 3: Średni czas -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold mb-2">Średni czas rozwiązywania (min)</h3>
                    <canvas id="avgDurationChart"></canvas>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function(){
                        const labels = @json($labels);
                        const avgScores = @json($avgScores);
                        const passRates = @json($passRates);
                        const avgDurations = @json($avgDurations);

                        // Wykres 1 - avgScore
                        new Chart(document.getElementById('avgScoreChart'), {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Średni wynik (pkt)',
                                    data: avgScores,
                                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                                }]
                            },
                            options: {
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });

                        // Wykres 2 - passRate
                        new Chart(document.getElementById('passRateChart'), {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: '% zdawalności',
                                    data: passRates,
                                    backgroundColor: 'rgba(255, 99, 132, 0.6)'
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100
                                    }
                                }
                            }
                        });

                        // Wykres 3 - avgDuration
                        new Chart(document.getElementById('avgDurationChart'), {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Średni czas (min)',
                                    data: avgDurations,
                                    backgroundColor: 'rgba(255, 206, 86, 0.6)'
                                }]
                            },
                            options: {
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });
                    });
                </script>
            @endif
        </div>
    </div>
</x-app-layout>
