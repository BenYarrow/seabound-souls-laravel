<?php

// A UI-only Filament field that opens a Mapbox modal; clicking the map writes the
// chosen coordinates into two sibling fields (latitude/longitude) in the same
// form container — so it works standalone AND inside repeater rows. It stores
// nothing of its own (dehydrated(false)).

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class MapCoordinatePicker extends Field
{
    protected string $view = 'filament.forms.components.map-coordinate-picker';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    protected function setUp(): void
    {
        parent::setUp();
        // Purely a control — never persisted or hydrated.
        $this->dehydrated(false);
    }

    /** Relative name of the latitude sibling field (default 'latitude'). */
    public function latitudeField(string $name): static
    {
        $this->latitudeField = $name;

        return $this;
    }

    /** Relative name of the longitude sibling field (default 'longitude'). */
    public function longitudeField(string $name): static
    {
        $this->longitudeField = $name;

        return $this;
    }

    /** Absolute Livewire state path of the latitude sibling. */
    public function getLatitudePath(): string
    {
        return $this->resolveSiblingPath($this->latitudeField);
    }

    /** Absolute Livewire state path of the longitude sibling. */
    public function getLongitudePath(): string
    {
        return $this->resolveSiblingPath($this->longitudeField);
    }

    protected function resolveSiblingPath(string $name): string
    {
        return self::siblingPathFor($this->getStatePath(), $name);
    }

    /**
     * Siblings share this field's parent container path. Strip the leaf segment
     * of $statePath and append the sibling's name — handles both top-level forms
     * and nested repeater rows (paths like data.repeater.uuid.field). Pure and
     * static so the derivation is unit-testable without a mounted form.
     */
    public static function siblingPathFor(string $statePath, string $name): string
    {
        $lastDot = strrpos($statePath, '.');
        $parent = $lastDot === false ? '' : substr($statePath, 0, $lastDot);

        return $parent === '' ? $name : $parent . '.' . $name;
    }
}
