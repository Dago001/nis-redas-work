<?php

namespace App\Console\Commands;

use App\Models\ReportingUnit;
use Illuminate\Console\Command;

class AssignReportingUnitCommand extends Command
{
    protected $signature = 'redas:reporting-units:assign {code : Child reporting unit code} {parent : Parent reporting unit code}';

    protected $description = 'Assign a reporting unit (formation or section) to its parent zone or directorate';

    public function handle(): int
    {
        $child = ReportingUnit::query()->where('code', $this->argument('code'))->first();
        $parent = ReportingUnit::query()->where('code', $this->argument('parent'))->first();

        if (! $child) {
            $this->error("Child reporting unit [{$this->argument('code')}] not found.");

            return self::FAILURE;
        }

        if (! $parent) {
            $this->error("Parent reporting unit [{$this->argument('parent')}] not found.");

            return self::FAILURE;
        }

        $child->parent_id = $parent->id;
        $child->save();

        $this->info("Assigned [{$child->code}] ({$child->name}) under [{$parent->code}] ({$parent->name}).");

        return self::SUCCESS;
    }
}
