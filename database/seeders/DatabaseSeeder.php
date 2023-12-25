<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WhatsappComplaint;
use App\Models\WhatsappRecoveryIndex;
use App\Models\WhatsappMainMenu;
use App\Models\WhatsappBot;
use App\Models\WhatsappSatisfactionSurvey;
use App\Models\FailedTherapy;
use App\Models\KuesionerMenungguObat;
use App\Models\WhatsappBpjsDentistRegistration;
use App\Models\WhatsappJadwalKonsultasiInquiry;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $no_telp = '6283845655991';
        WhatsappComplaint::where('no_telp', $no_telp)->delete();
        WhatsappRecoveryIndex::where('no_telp', $no_telp)->delete();
        WhatsappMainMenu::where('no_telp', $no_telp)->delete();
        WhatsappBot::where('no_telp', $no_telp)->delete();
        WhatsappSatisfactionSurvey::where('no_telp', $no_telp)->delete();
        FailedTherapy::where('no_telp', $no_telp)->delete();
        KuesionerMenungguObat::where('no_telp', $no_telp)->delete();
        WhatsappBpjsDentistRegistration::where('no_telp', $no_telp)->delete();
        WhatsappJadwalKonsultasiInquiry::where('no_telp', $no_telp)->delete();
    }
}
