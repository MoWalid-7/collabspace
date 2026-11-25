<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ActivityEvent;
use App\Enums\ChannelType;
use App\Enums\FileCategory;
use App\Enums\MeetingParticipantResponse;
use App\Enums\MeetingStatus;
use App\Enums\NotificationType;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\DailyStatus;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Meeting;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DomainNotification;
use App\Services\Storage\Contracts\FileStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding Collabspace Demo data...');

        $now = CarbonImmutable::now();
        $password = Hash::make('password');

        // 1. Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@collabspace.test'],
            ['name' => 'Sara Al-Mansoor', 'password' => $password, 'avatar' => null]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@collabspace.test'],
            ['name' => 'Omar Farooq', 'password' => $password, 'avatar' => null]
        );

        $members = [
            'leila' => User::firstOrCreate(['email' => 'leila@collabspace.test'], ['name' => 'Leila Chen', 'password' => $password, 'avatar' => null]),
            'tariq' => User::firstOrCreate(['email' => 'tariq@collabspace.test'], ['name' => 'Tariq Al-Sabah', 'password' => $password, 'avatar' => null]),
            'maya' => User::firstOrCreate(['email' => 'maya@collabspace.test'], ['name' => 'Maya Patel', 'password' => $password, 'avatar' => null]),
            'khalid' => User::firstOrCreate(['email' => 'khalid@collabspace.test'], ['name' => 'Khalid Ibrahim', 'password' => $password, 'avatar' => null]),
            'zara' => User::firstOrCreate(['email' => 'zara@collabspace.test'], ['name' => 'Zara Morales', 'password' => $password, 'avatar' => null]),
        ];

        // 2. Workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'collabspace-demo'],
            ['name' => 'Collabspace Demo', 'owner_id' => $admin->id]
        );

        // Attach members to workspace
        $workspace->members()->syncWithoutDetaching([
            $admin->id => ['role' => WorkspaceRole::Admin->value],
            $manager->id => ['role' => WorkspaceRole::Manager->value],
            $members['leila']->id => ['role' => WorkspaceRole::Member->value],
            $members['tariq']->id => ['role' => WorkspaceRole::Member->value],
            $members['maya']->id => ['role' => WorkspaceRole::Member->value],
            $members['khalid']->id => ['role' => WorkspaceRole::Member->value],
            $members['zara']->id => ['role' => WorkspaceRole::Member->value],
        ]);

        // 3. Teams with role_titles
        $teamsData = [
            ['name' => 'UI/UX Design', 'description' => 'User research and product interface design', 'lead' => $members['leila'], 'members' => [
                $members['leila']->id => 'Design Lead',
                $members['maya']->id => 'Product Designer',
            ]],
            ['name' => 'Frontend Engineering', 'description' => 'Web applications and reactive clients', 'lead' => $members['tariq'], 'members' => [
                $members['tariq']->id => 'Frontend Lead',
                $members['zara']->id => 'Frontend Developer',
            ]],
            ['name' => 'Backend Engineering', 'description' => 'APIs, architecture, and cloud data stores', 'lead' => $members['khalid'], 'members' => [
                $members['khalid']->id => 'Backend Lead',
                $manager->id => 'Senior Systems Engineer',
            ]],
            ['name' => 'Product & Marketing', 'description' => 'Go-to-market and growth optimization', 'lead' => $manager, 'members' => [
                $manager->id => 'Product Manager',
                $admin->id => 'Executive Sponsor',
            ]],
        ];

        $teams = [];
        foreach ($teamsData as $data) {
            $team = Team::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $data['name']],
                ['description' => $data['description'], 'lead_id' => $data['lead']->id]
            );
            $syncMembers = [];
            foreach ($data['members'] as $uId => $roleTitle) {
                $syncMembers[$uId] = ['role_title' => $roleTitle];
            }
            $team->members()->syncWithoutDetaching($syncMembers);
            $teams[$data['name']] = $team;
        }

        // 4. Projects
        $alphaProject = Project::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Project Alpha (Core SaaS)'],
            [
                'description' => 'Flagship multi-tenant SaaS workspace and analytics suite.',
                'type' => 'Software',
                'priority' => ProjectPriority::High,
                'status' => ProjectStatus::Active,
                'start_date' => $now->subMonths(3)->toDateString(),
                'deadline' => $now->addMonths(2)->toDateString(),
                'created_by' => $admin->id,
            ]
        );
        $alphaProject->members()->syncWithoutDetaching([
            $admin->id => ['role_title' => 'Project Sponsor'],
            $manager->id => ['role_title' => 'Scrum Master'],
            $members['khalid']->id => ['role_title' => 'Tech Lead'],
            $members['tariq']->id => ['role_title' => 'Frontend Specialist'],
        ]);
        $alphaProject->teams()->syncWithoutDetaching([$teams['Frontend Engineering']->id, $teams['Backend Engineering']->id]);

        $sepetGoProject = Project::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'SepetGo QuickCommerce Integration'],
            [
                'description' => 'Omnichannel grocery checkout and fast shipping integration.',
                'type' => 'Integration',
                'priority' => ProjectPriority::Medium,
                'status' => ProjectStatus::Done,
                'start_date' => $now->subMonths(8)->toDateString(),
                'deadline' => $now->subMonths(1)->toDateString(),
                'created_by' => $manager->id,
            ]
        );
        $sepetGoProject->members()->syncWithoutDetaching([
            $manager->id => ['role_title' => 'Lead Manager'],
            $members['zara']->id => ['role_title' => 'Web Integrator'],
            $members['khalid']->id => ['role_title' => 'API Architect'],
        ]);
        $sepetGoProject->teams()->syncWithoutDetaching([$teams['Backend Engineering']->id]);

        $plannedProject = Project::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'CollabAI Copilot Initiative'],
            [
                'description' => 'Next-generation intelligent agent sidecar and automated summaries.',
                'type' => 'R&D',
                'priority' => ProjectPriority::High,
                'status' => ProjectStatus::Planned,
                'start_date' => $now->addMonth()->toDateString(),
                'deadline' => $now->addMonths(5)->toDateString(),
                'created_by' => $admin->id,
            ]
        );
        $plannedProject->members()->syncWithoutDetaching([
            $admin->id => ['role_title' => 'Director'],
            $members['leila']->id => ['role_title' => 'UX Researcher'],
        ]);
        $plannedProject->teams()->syncWithoutDetaching([$teams['UI/UX Design']->id]);

        // 5. ~40 Tasks with realistic data & historical spreads
        $allUsers = [$admin, $manager, ...array_values($members)];
        $tasksData = [
            // Overdue tasks
            ['title' => 'Fix race condition in webhook dispatch', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'progress' => 40, 'due' => $now->subDays(4), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Audit SSL cipher suites on production load balancer', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High, 'progress' => 0, 'due' => $now->subDays(2), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Update user privacy policy for GDPR compliance', 'status' => TaskStatus::InReview, 'priority' => TaskPriority::Medium, 'progress' => 85, 'due' => $now->subDays(1), 'completed_at' => null, 'proj' => $alphaProject],

            // Due this week
            ['title' => 'Implement dashboard WebSocket reconnection backoff', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'progress' => 60, 'due' => $now->addDays(2), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Refactor file upload chunking pipeline', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'progress' => 50, 'due' => $now->addDays(3), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Prepare onboarding walkthrough slides for sales engineers', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Low, 'progress' => 10, 'due' => $now->addDays(4), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Conduct usability tests on responsive sidebar menu', 'status' => TaskStatus::InReview, 'priority' => TaskPriority::Medium, 'progress' => 90, 'due' => $now->addDays(1), 'completed_at' => null, 'proj' => $alphaProject],

            // Active / Todo tasks
            ['title' => 'Design empty-state illustrations for task board', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Low, 'progress' => 0, 'due' => $now->addWeeks(2), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Implement OAuth token refresh queue worker', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::Medium, 'progress' => 70, 'due' => $now->addWeeks(3), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Write end-to-end performance benchmark script', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Medium, 'progress' => 0, 'due' => $now->addWeeks(2), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Configure automated nightly database snapshots', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'progress' => 30, 'due' => $now->addWeeks(1), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Optimize fulltext search index for customer comments', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Medium, 'progress' => 0, 'due' => $now->addWeeks(4), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Draft dark mode color tokens palette', 'status' => TaskStatus::InReview, 'priority' => TaskPriority::Medium, 'progress' => 80, 'due' => $now->addWeeks(1), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Add rate-limiting telemetry headers', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Low, 'progress' => 0, 'due' => $now->addWeeks(2), 'completed_at' => null, 'proj' => $alphaProject],
            ['title' => 'Setup automated status report cron dispatcher', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'progress' => 45, 'due' => $now->addDays(5), 'completed_at' => null, 'proj' => $alphaProject],

            // Planned project tasks
            ['title' => 'Draft PRD for AI Copilot chat integration', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High, 'progress' => 0, 'due' => $now->addMonths(1), 'completed_at' => null, 'proj' => $plannedProject],
            ['title' => 'Evaluate vector embedding store options', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'progress' => 35, 'due' => $now->addWeeks(3), 'completed_at' => null, 'proj' => $plannedProject],
            ['title' => 'Survey member prompts and recurring meeting patterns', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Medium, 'progress' => 0, 'due' => $now->addWeeks(2), 'completed_at' => null, 'proj' => $plannedProject],
        ];

        // Add 24 completed tasks spread across the last 12 months for rich dashboard charts & reports
        for ($month = 0; $month < 12; $month++) {
            $completedDate = $now->subMonths($month)->setDay(rand(5, 25))->setTime(rand(10, 18), 0);
            $tasksData[] = [
                'title' => "Deploy delivery microservice batch v{$month}.1",
                'status' => TaskStatus::Completed,
                'priority' => TaskPriority::Medium,
                'progress' => 100,
                'due' => $completedDate->subDays(2),
                'completed_at' => $completedDate,
                'proj' => ($month % 2 === 0) ? $sepetGoProject : $alphaProject,
            ];
            $tasksData[] = [
                'title' => "Quarterly system validation milestone {$month}",
                'status' => TaskStatus::Completed,
                'priority' => TaskPriority::High,
                'progress' => 100,
                'due' => $completedDate->subDay(),
                'completed_at' => $completedDate,
                'proj' => $alphaProject,
            ];
        }

        $createdTasks = [];
        foreach ($tasksData as $idx => $t) {
            $creator = $allUsers[$idx % count($allUsers)];
            $assignee = $allUsers[($idx + 1) % count($allUsers)];
            $reviewer = $allUsers[($idx + 2) % count($allUsers)];

            $task = Task::create([
                'workspace_id' => $workspace->id,
                'project_id' => $t['proj']->id,
                'title' => $t['title'],
                'description' => "Detailed task description for {$t['title']}. Ensuring high quality and robust delivery.",
                'status' => $t['status'],
                'priority' => $t['priority'],
                'progress' => $t['progress'],
                'due_date' => $t['due']?->toDateString(),
                'completed_at' => $t['completed_at'],
                'created_by' => $creator->id,
                'reviewer_id' => $reviewer->id,
                'position' => $idx + 1,
                'created_at' => $t['completed_at'] ? $t['completed_at']->subWeeks(2) : $now->subDays(rand(1, 20)),
            ]);

            $task->assignees()->syncWithoutDetaching([$assignee->id]);
            $createdTasks[] = $task;
        }

        // 6. Comments on tasks
        $sampleComments = [
            'Reviewed the implementation; looks solid and cleanly tested.',
            'Please verify that all edge cases for boundary timestamps are handled.',
            'Dependencies updated and verified on local environment.',
            'Approved! Merging into main branch.',
        ];
        foreach (array_slice($createdTasks, 0, 8) as $cIdx => $task) {
            Comment::create([
                'workspace_id' => $workspace->id,
                'commentable_type' => Task::class,
                'commentable_id' => $task->id,
                'user_id' => $allUsers[$cIdx % count($allUsers)]->id,
                'body' => $sampleComments[$cIdx % count($sampleComments)],
                'created_at' => $now->subDays(rand(1, 5)),
            ]);
        }

        // 7. Files with versions via FileStorage contract on private disk
        $fileStorage = app(FileStorage::class);
        $fileSamples = [
            ['name' => 'architecture_spec.pdf', 'mime' => 'application/pdf', 'category' => FileCategory::Document],
            ['name' => 'database_schema_v2.sql', 'mime' => 'text/plain', 'category' => FileCategory::Document],
        ];

        foreach ($fileSamples as $sample) {
            $fakeUploadedFile = UploadedFile::fake()->create($sample['name'], 25, $sample['mime']);
            $stored = $fileStorage->put($fakeUploadedFile, "workspaces/{$workspace->id}/files");

            $fileModel = File::create([
                'workspace_id' => $workspace->id,
                'project_id' => $alphaProject->id,
                'task_id' => $createdTasks[0]->id,
                'uploaded_by' => $admin->id,
                'name' => $sample['name'],
            ]);

            $v1 = FileVersion::create([
                'file_id' => $fileModel->id,
                'uploaded_by' => $admin->id,
                'version_number' => 1,
                'disk' => $stored->disk,
                'path' => $stored->path,
                'original_name' => $sample['name'],
                'mime' => $sample['mime'],
                'size' => 25 * 1024,
                'checksum' => hash('sha256', 'sample-content-v1'),
                'note' => 'Initial version',
                'created_at' => $now->subDays(7),
            ]);

            // Version 2
            $storedV2 = $fileStorage->put($fakeUploadedFile, "workspaces/{$workspace->id}/files");
            $v2 = FileVersion::create([
                'file_id' => $fileModel->id,
                'uploaded_by' => $manager->id,
                'version_number' => 2,
                'disk' => $storedV2->disk,
                'path' => $storedV2->path,
                'original_name' => $sample['name'],
                'mime' => $sample['mime'],
                'size' => 28 * 1024,
                'checksum' => hash('sha256', 'sample-content-v2'),
                'note' => 'Updated revision',
                'created_at' => $now->subDays(2),
            ]);
        }

        // 8. Time entries across the last weeks
        foreach (array_slice($createdTasks, 0, 10) as $tIdx => $task) {
            $user = $allUsers[$tIdx % count($allUsers)];
            $startTime = $now->subDays(rand(1, 14))->setTime(rand(9, 14), 0);
            $duration = rand(3600, 14400); // 1 to 4 hours

            TimeEntry::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'started_at' => $startTime,
                'ended_at' => $startTime->addSeconds($duration),
                'duration_seconds' => $duration,
                'note' => "Worked on {$task->title}",
            ]);
        }

        // 9. Channels with messages and threads
        $generalChannel = Channel::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'general'],
            ['type' => ChannelType::Public, 'created_by' => $admin->id]
        );
        $generalChannel->members()->syncWithoutDetaching(array_map(fn ($u) => $u->id, $allUsers));

        $m1 = Message::create([
            'workspace_id' => $workspace->id,
            'channel_id' => $generalChannel->id,
            'user_id' => $admin->id,
            'body' => 'Welcome to Collabspace Demo workspace! All modules are fully operational.',
            'created_at' => $now->subDays(3),
        ]);

        $m2 = Message::create([
            'workspace_id' => $workspace->id,
            'channel_id' => $generalChannel->id,
            'user_id' => $manager->id,
            'body' => 'Sprint 42 deliverables are ready for review. Great work everyone!',
            'created_at' => $now->subDays(2),
        ]);

        // Thread reply
        Message::create([
            'workspace_id' => $workspace->id,
            'channel_id' => $generalChannel->id,
            'user_id' => $members['tariq']->id,
            'parent_id' => $m2->id,
            'body' => 'Frontend bundle size dropped by 24% after lazy loading optimization.',
            'created_at' => $now->subDays(1),
        ]);

        // 10. Meetings this week
        $meetingsData = [
            [
                'title' => 'Weekly Engineering Architecture Sync',
                'description' => 'Cross-team discussion on DB performance and API schema versioning.',
                'starts' => $now->next('Wednesday')->setTime(10, 0),
                'ends' => $now->next('Wednesday')->setTime(11, 0),
                'join_url' => 'https://meet.google.com/collab-demo-sync',
                'proj' => $alphaProject,
            ],
            [
                'title' => 'SepetGo Project Retrospective',
                'description' => 'Post-launch feedback and metric tracking review.',
                'starts' => $now->next('Thursday')->setTime(15, 0),
                'ends' => $now->next('Thursday')->setTime(16, 0),
                'join_url' => 'https://meet.google.com/sepetgo-retro-demo',
                'proj' => $sepetGoProject,
            ],
            [
                'title' => 'Product Design Sprint Workshop',
                'description' => 'Interactive wireframing for CollabAI sidecar companion.',
                'starts' => $now->next('Friday')->setTime(14, 0),
                'ends' => $now->next('Friday')->setTime(15, 30),
                'join_url' => 'https://meet.google.com/ai-design-workshop',
                'proj' => $plannedProject,
            ],
        ];

        foreach ($meetingsData as $m) {
            $meeting = Meeting::create([
                'workspace_id' => $workspace->id,
                'project_id' => $m['proj']->id,
                'title' => $m['title'],
                'description' => $m['description'],
                'starts_at' => $m['starts'],
                'ends_at' => $m['ends'],
                'join_url' => $m['join_url'],
                'status' => MeetingStatus::Scheduled,
                'created_by' => $manager->id,
            ]);

            $meeting->participants()->syncWithoutDetaching([
                $admin->id => ['response' => MeetingParticipantResponse::Accepted->value],
                $manager->id => ['response' => MeetingParticipantResponse::Accepted->value],
                $members['khalid']->id => ['response' => MeetingParticipantResponse::Accepted->value],
                $members['leila']->id => ['response' => MeetingParticipantResponse::Pending->value],
            ]);
        }

        // 11. Daily statuses for the last days
        for ($dayOffset = 0; $dayOffset < 3; $dayOffset++) {
            $date = $now->subDays($dayOffset)->toDateString();
            DailyStatus::create([
                'workspace_id' => $workspace->id,
                'user_id' => $members['khalid']->id,
                'project_id' => $alphaProject->id,
                'date' => $date,
                'done_yesterday' => 'Refactored repository contracts and database indexes.',
                'plan_today' => 'Adding unit tests for reports generation pipeline.',
                'blockers' => $dayOffset === 0 ? 'Waiting on third-party webhook sandbox access.' : null,
            ]);

            DailyStatus::create([
                'workspace_id' => $workspace->id,
                'user_id' => $members['tariq']->id,
                'project_id' => $alphaProject->id,
                'date' => $date,
                'done_yesterday' => 'Implemented task drag-and-drop sort order recomputation.',
                'plan_today' => 'Connecting meeting audio status notifications.',
                'blockers' => null,
            ]);
        }

        // 12. Notifications & Activities
        $admin->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => DomainNotification::class,
            'data' => [
                'type' => NotificationType::MeetingInvited->value,
                'title' => 'Meeting Invitation',
                'body' => 'You have been invited to Weekly Engineering Architecture Sync.',
                'project_id' => $alphaProject->id,
                'task_id' => null,
                'file_id' => null,
                'actor' => ['id' => $manager->id, 'name' => $manager->name],
            ],
            'read_at' => null,
        ]);

        Activity::create([
            'workspace_id' => $workspace->id,
            'project_id' => $alphaProject->id,
            'causer_id' => $admin->id,
            'subject_type' => Project::class,
            'subject_id' => $alphaProject->id,
            'event' => ActivityEvent::ProjectCreated->value,
            'properties' => [
                'project_name' => $alphaProject->name,
                'actor_name' => $admin->name,
            ],
            'created_at' => $now->subDays(10),
        ]);

        // Print Demo Credentials
        $this->command?->info('');
        $this->command?->info('=====================================================');
        $this->command?->info('   COLLABSPACE DEMO CREDENTIALS');
        $this->command?->info('=====================================================');
        $this->command?->info(" Workspace:     Collabspace Demo (ID: {$workspace->id})");
        $this->command?->info(' Header:        X-Workspace-Id: '.$workspace->id);
        $this->command?->info(' Password:      password');
        $this->command?->info(' Admin:         admin@collabspace.test');
        $this->command?->info(' Manager:       manager@collabspace.test');
        $this->command?->info(' Members:       leila@collabspace.test, tariq@collabspace.test,');
        $this->command?->info('                maya@collabspace.test, khalid@collabspace.test, zara@collabspace.test');
        $this->command?->info('=====================================================');
        $this->command?->info('');
    }
}
