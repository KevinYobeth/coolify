<?php

use App\Livewire\Project\Application\General;
use App\Livewire\Project\Edit as ProjectEdit;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutDefer();
    $this->withoutVite();
    Server::flushIdentityMap();
    Queue::fake();
    config(['app.maintenance.driver' => 'file']);

    InstanceSettings::unguarded(fn () => InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['id' => 0, 'is_dns_validation_enabled' => false],
    ));

    $this->team = Team::factory()->create(['show_boarding' => false]);
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

it('dispatches project-name-changed after the project is renamed', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Old name']);

    Livewire::test(ProjectEdit::class, ['project_uuid' => $project->uuid])
        ->set('name', 'New name')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('project-name-changed', name: 'New name', uuid: $project->uuid);

    expect($project->fresh()->name)->toBe('New name');
});

it('does not dispatch project-name-changed when the new project name is invalid', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Old name']);

    Livewire::test(ProjectEdit::class, ['project_uuid' => $project->uuid])
        ->set('name', '')
        ->call('submit')
        ->assertNotDispatched('project-name-changed');

    expect($project->fresh()->name)->toBe('Old name');
});

it('dispatches team-updated after the team is renamed', function () {
    Livewire::test(TeamIndex::class)
        ->set('name', 'Renamed team')
        ->call('submit')
        ->assertDispatched('team-updated');

    expect($this->team->fresh()->name)->toBe('Renamed team');
});

it('does not dispatch team-updated when the team cannot be saved', function () {
    Livewire::test(TeamIndex::class)
        ->set('name', '')
        ->call('submit')
        ->assertNotDispatched('team-updated');
});

it('dispatches resource-name-changed after the application is renamed', function () {
    $server = Server::factory()->create(['team_id' => $this->team->id]);
    $destination = StandaloneDocker::query()->where('server_id', $server->id)->firstOrFail();
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);
    $application = Application::factory()->create([
        'name' => 'Old app',
        'environment_id' => $environment->id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
        'fqdn' => null,
        'redirect' => 'both',
        'build_pack' => 'nixpacks',
        'static_image' => 'nginx:alpine',
        'is_http_basic_auth_enabled' => false,
        'base_directory' => '/',
        'git_repository' => 'coollabsio/coolify',
        'git_branch' => 'main',
        'ports_exposes' => '3000',
        'swarm_replicas' => 1,
    ]);

    Livewire::test(General::class, ['application' => $application])
        ->set('name', 'New app')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('resource-name-changed', name: 'New app', uuid: $application->uuid);

    expect($application->fresh()->name)->toBe('New app');
});
