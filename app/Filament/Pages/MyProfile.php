<?php

// Self-service profile editor. A contributor edits ONLY their own public profile
// here (the record is always auth()->user(), so there is no cross-user write
// path). A notice explains the profile goes live only after a published guide —
// public presence earned by contributing.

namespace App\Filament\Pages;

use App\Filament\Forms\ContributorProfileForm;
use App\Models\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'My Profile';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.my-profile';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Only invited contributors have a public profile to edit. */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isContributor() ?? false;
    }

    /**
     * URL-level gate mirroring the nav gate above — a contributor navigating
     * away and an owner (or unauthenticated request) hitting the URL directly
     * must not reach a page whose record is always the current user.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isContributor() ?? false;
    }

    /** Fill the form from the current user's own attributes on first load. */
    public function mount(): void
    {
        $this->form->fill($this->resolveRecord()->attributesToArray());
    }

    /** The record edited here is always the current user. */
    public function resolveRecord(): User
    {
        return auth()->user();
    }

    /** Build the profile form bound to `data`, modelled on the current user. */
    public function form(Form $form): Form
    {
        return $form
            ->schema(ContributorProfileForm::schema())
            ->statePath('data')
            ->model($this->resolveRecord());
    }

    /**
     * Persist the form state to the current user. Invariant: the record is
     * always auth()->user() — there is no cross-user write path here.
     */
    public function save(): void
    {
        $this->resolveRecord()->update($this->form->getState());

        Notification::make()->title('Profile saved')->success()->send();
    }
}
