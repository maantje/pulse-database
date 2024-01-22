@php use Illuminate\Support\Str; @endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        :name="$title"
        title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-4">
                @foreach($graphs as $type => $aggregates)
                    @foreach($aggregates as $aggregate => $color)
                        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                            <div class="h-0.5 w-3 rounded-full" style="background-color: {{ $color }}"></div>
                            {{ str_replace('_', ' ', ucfirst($aggregate)) }} {{ $type }}
                        </div>
                    @endforeach
                @endforeach
            </div>
        </x-slot:actions>
    </x-pulse::card-header>
    @if ($connections->isEmpty())
        <x-pulse::no-results/>
    @else
        <div wire:poll.5s class="overflow-x-auto pb-px">
            @foreach ($connections as $slug => $connection)
                <div class="grid gap-4 mx-px mb-px mt-4">
                    <div wire:key="{{ $slug }}-{{ Str::slug($title) }}">
                        @php
                            $highest = $connection->aggregates->flatten()->max();
                        @endphp
                        <div class="grid grid-cols-{{count($values) + 1}} gap-3 text-center items-center">
                            <div class="flex">
                                <div wire:key="{{ $slug }}-indicator"
                                     class="flex items-center {{ $connections->count() > 1 ? 'py-2' : '' }}"
                                     title="{{ $connection->updated_at->fromNow() }}">
                                    @if ($connection->recently_reported)
                                        <div class="w-5 flex justify-center mr-1">
                                            <div class="h-1 w-1 bg-green-500 rounded-full animate-pulse"></div>
                                        </div>
                                    @else
                                        <x-pulse::icons.signal-slash class="w-5 h-5 stroke-red-500 mr-1"/>
                                    @endif
                                </div>
                                <div wire:key="{{ $slug }}-name"
                                     class="flex items-center pr-8 xl:pr-12 {{ $connections->count() > 1 ? 'py-2' : '' }} {{ !$connection->recently_reported ? 'opacity-25 animate-pulse' : '' }}">
                                    <x-pulse::icons.server class="w-6 h-6 mr-2 stroke-gray-500 dark:stroke-gray-400"/>
                                    <span class="text-base font-bold text-gray-600 dark:text-gray-300"
                                          title="Time: {{ number_format($time) }}ms; Run at: {{ $runAt }};">{{ $connection->name }}</span>
                                </div>
                            </div>
                            @foreach($values as $value)
                                <div class="flex flex-col justify-center @sm:block">
                                <span class="text-xl uppercase font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                    {{ number_format($connection->$value) }}
                                </span>
                                    <span class="text-xs uppercase font-bold text-gray-500 dark:text-gray-400">
                                    {{ str_replace('_', ' ', ucfirst($value)) }}
                                </span>
                                </div>
                            @endforeach
                        </div>

                        @if(count($graphs) > 0)
                        <div class="mt-3 relative">
                            <div
                                class="absolute -left-px -top-2 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded after:[--triangle-size:4px] after:border-l-purple-500 after:absolute after:right-[calc(-1*var(--triangle-size))] after:top-[calc(50%-var(--triangle-size))] after:border-t-[length:var(--triangle-size)] after:border-b-[length:var(--triangle-size)] after:border-l-[length:var(--triangle-size)] after:border-transparent">
                                {{ number_format($highest) }}
                            </div>
                            <div
                                wire:ignore
                                x-data="{
                                    init() {
                                        let chart = new Chart(
                                            this.$refs.canvas,
                                            {
                                                type: 'line',
                                                data: {
                                                    labels: @js($connection->aggregates->first()->first()->keys()),
                                                    datasets: [
                                                        @foreach($graphs as $type => $aggregates)
                                                            @foreach($connection->aggregates[$type] as $key => $data)
                                                                {
                                                                    label: '{{ str_replace('_', ' ', ucfirst($key)) }} {{ $type }}',
                                                                    borderColor: '{{ $aggregates[$key] }}',
                                                                    data: @js($data),
                                                                },
                                                            @endforeach
                                                        @endforeach
                                                    ],
                                                },
                                                options: {
                                                    maintainAspectRatio: false,
                                                    layout: {
                                                        autoPadding: false,
                                                        padding: {
                                                            top: 1,
                                                        },
                                                    },
                                                    datasets: {
                                                        line: {
                                                            borderWidth: 2,
                                                            borderCapStyle: 'round',
                                                            pointHitRadius: 10,
                                                            pointStyle: false,
                                                            tension: 0.2,
                                                            spanGaps: false,
                                                            segment: {
                                                                borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                                                            }
                                                        }
                                                    },
                                                    scales: {
                                                        x: {
                                                            display: false,
                                                        },
                                                        y: {
                                                            display: false,
                                                            min: 0,
                                                        },
                                                    },
                                                    plugins: {
                                                        legend: {
                                                            display: false,
                                                        },
                                                        tooltip: {
                                                            mode: 'index',
                                                            position: 'nearest',
                                                            intersect: false,
                                                            callbacks: {
                                                                beforeBody: (context) => context
                                                                    .map(item => `${item.dataset.label}: ${item.formattedValue}`)
                                                                    .join(', '),
                                                                label: () => null,
                                                            },
                                                        },
                                                    },
                                                },
                                            }
                                        )

                                        Livewire.on('{{Str::slug($title)}}-database-chart-update', ({ connections }) => {
                                            if (chart === undefined) {
                                                return
                                            }

                                            if (connections['{{ $slug }}'] === undefined && chart) {
                                                chart.destroy()
                                                chart = undefined
                                                return
                                            }

                                            @foreach($graphs as $type => $aggregates)
                                                @foreach($aggregates as $key => $color)
                                                    @if($loop->first)
                                                        chart.data.labels = Object.keys(connections['{{ $slug }}']['aggregates']['{{ $type }}']['{{ $key }}'])
                                                    @endif
                                                    chart.data.datasets[{{ $loop->index }}].data = Object.values(connections['{{ $slug }}']['aggregates']['{{ $type }}']['{{ $key }}'])
                                                @endforeach
                                            @endforeach

                                            chart.update()
                                        })
                                    }
                                }"
                            >
                                <canvas x-ref="canvas"
                                        class="ring-1 h-12 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    @endif
</x-pulse::card>
