<?php

namespace Maantje\Pulse\Database\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;

class DatabaseRecorder
{
    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = SharedBeat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
        protected DatabaseManager $manager,
    ) {
        //
    }

    public function record(SharedBeat $event): void
    {
        $class = self::class;

        foreach ($this->config->get('pulse.recorders.'.self::class.'.connections', []) as $connectionName => $config) {
            $connection = $this->manager->connection($connectionName);

            $keys = collect()
                ->merge($this->config->get("pulse.recorders.$class.connections.$connectionName.values", []))
                ->merge($maxAggregates = $this->config->get("pulse.recorders.$class.connections.$connectionName.aggregates.max", []))
                ->merge($avgAggregates = $this->config->get("pulse.recorders.$class.connections.$connectionName.aggregates.avg", []))
                ->merge($countAggregates = $this->config->get("pulse.recorders.$class.connections.$connectionName.aggregates.count", []));

            $status = collect($connection->select('show status'))
                ->filter(function ($row) use ($keys) {
                    return $keys->contains($row->Variable_name);
                })
                ->mapWithKeys(function ($row) {
                    return [$row->Variable_name => $row->Value];
                });


            $slug = Str::slug($connection->getName());

            foreach ($maxAggregates as $max) {
                $this->pulse->record($max, $slug, $status->get($max), $event->time)->max()->onlyBuckets();
            }

            foreach ($avgAggregates as $avg) {
                $this->pulse->record($avg, $slug, $status->get($avg), $event->time)->avg()->onlyBuckets();
            }

            foreach ($countAggregates as $count) {
                $this->pulse->record($count, $slug, $status->get($count), $event->time)->count()->onlyBuckets();
            }

            $this->pulse->set('database_connection', $slug, json_encode([
                ...$status,
                'name' => $connection->getName(),
            ]));
        }
    }
}
