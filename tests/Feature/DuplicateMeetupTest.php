<?php

namespace Tests\Feature;

use App\Actions\GenerateOpenGraphImage;
use App\Enums\GroupRole;
use App\Filament\Resources\MeetupResource;
use App\Filament\Resources\MeetupResource\Pages\CreateMeetup;
use App\Filament\Resources\MeetupResource\Pages\EditMeetup;
use App\Filament\Resources\MeetupResource\Pages\ListMeetups;
use App\Models\Group;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DuplicateMeetupTest extends TestCase
{
    use RefreshDatabase;

    protected Group $group;

    protected Meetup $meetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();
        app()->instance(Group::class, $this->group);
        app()->instance("group:{$this->group->domain}", $this->group);

        $user = User::factory()->create();
        $this->group->users()->attach($user, ['role' => GroupRole::Admin]);
        $this->actingAs($user);

        $this->meetup = Meetup::factory()->for($this->group)->create([
            'location' => 'Original Location',
            'capacity' => 42,
            'description' => 'Original description',
            'external_rsvp_url' => 'https://example.com/rsvp',
            'starts_at' => now()->subMonth()->hour(18)->minute(30)->second(0),
            'ends_at' => now()->subMonth()->hour(20)->minute(0)->second(0),
        ]);
    }

    public function test_duplicate_attributes_move_dates_to_next_week_and_keep_time_of_day(): void
    {
        $attributes = $this->meetup->toDuplicateAttributes();

        $this->assertSame('Original Location', $attributes['location']);
        $this->assertSame(42, $attributes['capacity']);
        $this->assertSame('Original description', $attributes['description']);
        $this->assertSame('https://example.com/rsvp', $attributes['external_rsvp_url']);
        $this->assertTrue($attributes['starts_at']->isSameDay(now()->addWeek()));
        $this->assertSame('18:30', $attributes['starts_at']->format('H:i'));
        $this->assertSame('20:00', $attributes['ends_at']->format('H:i'));
        $this->assertTrue($this->meetup->fresh()->starts_at->isPast(), 'Source meetup must not be mutated');
    }

    public function test_create_page_is_prefilled_from_the_duplicated_meetup(): void
    {
        GenerateOpenGraphImage::mock()->shouldReceive('handle')->once()->andReturn('og.png');

        Livewire::withQueryParams(['duplicate' => $this->meetup->getKey()])
            ->test(CreateMeetup::class)
            ->assertFormSet([
                'group_id' => $this->group->getKey(),
                'location' => 'Original Location',
                'capacity' => 42,
                'description' => 'Original description',
                'external_rsvp_url' => 'https://example.com/rsvp',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Meetup::query()->count());
        $this->assertSame(1, Meetup::query()->whereKeyNot($this->meetup->getKey())->where('location', 'Original Location')->count());
    }

    public function test_create_page_ignores_unknown_duplicate_ids(): void
    {
        Livewire::withQueryParams(['duplicate' => '123'])
            ->test(CreateMeetup::class)
            ->assertFormSet(['description' => null])
            ->assertSuccessful();
    }

    public function test_duplicate_action_is_available_on_the_table_and_edit_page(): void
    {
        $url = MeetupResource::getUrl('create', ['duplicate' => $this->meetup->getKey()]);

        Livewire::test(ListMeetups::class)
            ->assertTableActionExists('duplicate')
            ->assertTableActionHasUrl('duplicate', $url, $this->meetup);

        Livewire::test(EditMeetup::class, ['record' => $this->meetup->getKey()])
            ->assertActionExists('duplicate')
            ->assertActionHasUrl('duplicate', $url);
    }
}
