<?php

namespace AdAstra\Doctor\Checks\Templates;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Models\EntryTree;
use AdAstra\Models\EntryType;
use Illuminate\Support\Facades\View;

class EntryTemplatesCheck extends AbstractDoctorCheck
{
    protected string $id = 'templates.entry-templates';
    protected string $name = 'Referenced entry templates';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        // template name => list of referencing sources. A stored name that
        // doesn't resolve through the templates:: namespace is a 500 on the
        // public site the first time that entry/node is visited.
        $refs = [];

        foreach (EntryType::whereNotNull('default_template')->get(['handle', 'default_template']) as $type) {
            $refs[$type->default_template][] = "entry type [{$type->handle}]";
        }

        foreach (EntryTree::whereNotNull('template')->get(['id', 'template']) as $node) {
            $refs[$node->template][] = "tree node #{$node->id}";
        }

        $broken = 0;

        foreach ($refs as $template => $sources) {
            if (!View::exists('templates::' . $template)) {
                $broken++;
                yield $this->fail(
                    "Template [{$template}] not found (referenced by " . implode(', ', $sources) . ')',
                    fixCommand: 'create the template under resources/templates or fix the reference',
                );
            }
        }

        // Framework fallbacks (e.g. EntryTreeRouteDriver's hard-coded
        // entries.show). Missing is a latent 500, not a broken configured
        // reference, so it warns rather than fails.
        $missing = 0;

        foreach ((array) config('doctor.required_templates', []) as $template) {
            if (!View::exists('templates::' . $template)) {
                $missing++;
                yield $this->warn(
                    "Fallback template [{$template}] does not exist — routes that fall through to it will 500",
                    fixCommand: 'create the template under resources/templates',
                );
            }
        }

        if ($broken === 0 && $missing === 0) {
            yield $this->pass('All referenced templates exist');
        }
    }
}
