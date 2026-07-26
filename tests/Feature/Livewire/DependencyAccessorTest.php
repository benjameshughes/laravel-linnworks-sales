<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Livewire skips boot() on the lazy-load request, so any component that takes
 * dependencies must still be able to reach them without boot() having run.
 * A component that touches the property directly blows up with
 * "Typed property ... must not be accessed before initialization".
 */
function componentsWithDependencies(): array
{
    $cases = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()->in(app_path('Livewire'))->name('*.php') as $file) {
        $source = $file->getContents();

        if (! str_contains($source, 'public function boot(')) {
            continue;
        }

        preg_match('/namespace ([^;]+);/', $source, $ns);
        preg_match('/class (\w+)/', $source, $cls);
        $class = $ns[1].'\\'.$cls[1];

        preg_match_all('/private \?(\w+) \$(\w+) = null;/', $source, $props, PREG_SET_ORDER);

        foreach ($props as [, $type, $property]) {
            $cases[] = [$class, $property];
        }
    }

    return $cases;
}

it('reaches every injected dependency without boot() having run', function () {
    $cases = componentsWithDependencies();

    expect($cases)->not->toBeEmpty();

    foreach ($cases as [$class, $property]) {
        $component = new $class;

        // Deliberately skip boot() - this is what Livewire does when lazy loading
        expect(method_exists($component, $property))
            ->toBeTrue("{$class} must expose a {$property}() accessor, not just the property");

        $accessor = new ReflectionMethod($component, $property);
        $accessor->setAccessible(true);

        $resolved = $accessor->invoke($component);

        expect($resolved)->not->toBeNull("{$class}::{$property}() resolved to null without boot()");
    }
});

it('never reads an injected dependency as a bare property', function () {
    $offenders = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()->in(app_path('Livewire'))->name('*.php') as $file) {
        $source = $file->getContents();

        if (! str_contains($source, 'public function boot(')) {
            continue;
        }

        preg_match_all('/private \?\w+ \$(\w+) = null;/', $source, $props);

        foreach ($props[1] as $property) {
            // $this->prop is only legal as an assignment target or inside the accessor
            preg_match_all('/\$this->'.$property.'\b(?!\s*\()/', $source, $uses, PREG_OFFSET_CAPTURE);

            foreach ($uses[0] as [, $offset]) {
                $context = substr($source, max(0, $offset - 40), 90);

                if (str_contains($context, '??=') || str_contains($context, '$this->'.$property.' = $')) {
                    continue;
                }

                $offenders[] = $file->getRelativePathname().' -> $this->'.$property;
            }
        }
    }

    expect($offenders)->toBe([]);
});
