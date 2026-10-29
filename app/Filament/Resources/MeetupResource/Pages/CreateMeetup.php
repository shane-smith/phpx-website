<?php

namespace App\Filament\Resources\MeetupResource\Pages;

use App\Actions\GenerateOpenGraphImage;
use App\Filament\Resources\MeetupResource;
use App\Models\Meetup;
use Filament\Resources\Pages\CreateRecord;

class CreateMeetup extends CreateRecord
{
	protected static string $resource = MeetupResource::class;
	
	protected function fillForm(): void
	{
		$this->callHook('beforeFill');

		$source = Meetup::query()->find(request()->query('duplicate'));

		$this->form->fill($source ? $this->getDuplicatedData($source) : []);

		$this->callHook('afterFill');
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getDuplicatedData(Meetup $source): array
	{
		return [
			'group_id' => $source->group_id,
			'location' => $source->location,
			'capacity' => $source->capacity,
			'description' => $source->description,
			'external_rsvp_url' => $source->external_rsvp_url,
			'custom_open_graph_image' => $source->custom_open_graph_image,
			'starts_at' => $source->starts_at->avoidMutation()->setDateFrom(now()->addWeek()),
			'ends_at' => $source->ends_at->avoidMutation()->setDateFrom(now()->addWeek()),
		];
	}

	protected function afterCreate()
	{
		GenerateOpenGraphImage::run($this->getRecord());
	}
	
	protected function mutateFormDataBeforeCreate(array $data): array
	{
		if ($group = request()->attributes->get('group')) {
			$data['group_id'] = $group->getKey();
		}
		
		return $data;
	}
}
