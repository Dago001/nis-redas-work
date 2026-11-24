<?php

namespace App\Console\Commands;

use App\Models\ReportingUnit;
use Illuminate\Console\Command;

class AddReportingUnitCommand extends Command
{
    protected $signature = 'redas:reporting-units:add
        {code : Unique reporting unit code}
        {name : Display name of the reporting unit}
        {type : Unit type (zone|formation|directorate|section|cgis_unit|headquarters)}
        {--parent= : Optional parent unit code}
        {--formation-type= : Optional formation type (state_command|airport_command|border_formation|other)}';

    protected $description = 'Register a new reporting unit (section, airport command, border formation, etc.)';

    public function handle(): int
    {
        $allowedTypes = ['zone', 'formation', 'directorate', 'section', 'cgis_unit', 'headquarters'];
        $type = (string) $this->argument('type');

        if (! in_array($type, $allowedTypes, true)) {
            $this->error('Invalid type. Allowed: '.implode(', ', $allowedTypes));

            return self::FAILURE;
        }

        $parentId = null;
        if ($this->option('parent')) {
            $parent = ReportingUnit::query()->where('code', (string) $this->option('parent'))->first();
            if (! $parent) {
                $this->error("Parent unit [{$this->option('parent')}] not found.");

                return self::FAILURE;
            }
            $parentId = $parent->id;
        }

        $unit = ReportingUnit::updateOrCreate(
            ['code' => (string) $this->argument('code')],
            [
                'name' => (string) $this->argument('name'),
                'type' => $type,
                'formation_type' => $this->option('formation-type') ?: null,
                'parent_id' => $parentId,
                'is_active' => true,
            ]
        );

        $this->info("Registered reporting unit [{$unit->code}] ({$unit->name}) as {$unit->type}.");

        return self::SUCCESS;
    }
}
