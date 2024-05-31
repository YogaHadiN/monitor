<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class clearAllWhatsappBotServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear_wabot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        \App\Models\WhatsappComplaint::truncate();
        \App\Models\WhatsappRecoveryIndex::truncate();
        \App\Models\WhatsappMainMenu::truncate();
        \App\Models\WhatsappBot::truncate();
        \App\Models\WhatsappSatisfactionSurvey::truncate();
        \App\Models\FailedTherapy::truncate();
        \App\Models\KuesionerMenungguObat::truncate();
        \App\Models\WhatsappBpjsDentistRegistration::truncate();
        \App\Models\WhatsappJadwalKonsultasiInquiry::truncate();
    }
}
