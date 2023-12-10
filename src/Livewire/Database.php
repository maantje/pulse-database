<?php

namespace Maantje\Pulse\Database\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Livewire\Attributes\Lazy;
use Maantje\Pulse\Database\Recorders\DatabaseRecorder;

class Database extends Card
{
    use HasPeriod;
    use RemembersQueries;

    public array $graphs = [];
    public array $values;
    public string $title;

    public function __construct()
    {

    }

    #[Lazy]
    public function render()
    {
        [$connections, $time, $runAt] = $this->remember(function () {
            $graphs = [];

            foreach ($this->graphs as $aggregate => $graph) {
                $graphs[$aggregate] = Pulse::graph(array_keys($graph), $aggregate, $this->periodAsInterval());
            }


            return Pulse::values('database_connection')
                ->map(function ($fpm, $slug) use ($graphs) {
                    $values = json_decode($fpm->value, flags: JSON_THROW_ON_ERROR);

                    return (object) [
                        ...((array) $values),
                        'aggregates' => collect($this->graphs)->reduce(function (Collection $carry, $aggregates, $type) use ($slug, $graphs) {
                            $carry[$type] = collect(array_keys($aggregates))->mapWithKeys(function ($value) use ($slug, $type, $graphs) {
                                return [$value => $graphs[$type]->get($slug)?->get($value) ?? collect()];
                            });

                            return $carry;
                        }, collect()),
                        'updated_at' => $updatedAt = CarbonImmutable::createFromTimestamp($fpm->timestamp),
                        'recently_reported' => $updatedAt->isAfter(now()->subSeconds(30)),
                    ];
                });
        }, Str::slug($this->title));

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch(Str::slug($this->title). '-database-chart-update', connections: $connections);
        }

        return View::make('database::livewire.database-card', [
            'connections' => $connections,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
