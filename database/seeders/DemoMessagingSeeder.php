<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Database\Seeder;

class DemoMessagingSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::firstOrCreate(['email' => 'client@skillbridge.test'], [
            'name' => 'Demo Client', 'password' => 'password', 'role' => 'client',
        ]);
        $freelancer = User::firstOrCreate(['email' => 'freelancer@skillbridge.test'], [
            'name' => 'Demo Freelancer', 'password' => 'password', 'role' => 'freelancer',
        ]);
        $project = Project::firstOrCreate(['client_id' => $client->id, 'title' => 'SkillBridge messaging demo'], [
            'description' => 'A sample project for trying private conversations and deadline notifications.',
            'budget' => 5000, 'deadline' => today()->addDay(),
        ]);
        if (! Conversation::where('project_id', $project->id)->exists()) {
            $conversation = app(ConversationService::class)->start($client, [
                'project_id' => $project->id, 'recipient_id' => $freelancer->id,
                'body' => 'Hello! Can you help with this project? Please send your availability.',
            ]);
            app(ConversationService::class)->send($conversation, $freelancer, 'Happy to help. I am available this week.');
        }

        $this->command->info('Demo accounts: client@skillbridge.test and freelancer@skillbridge.test / password');
    }
}
