<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Credential;
use Input;
use App\Models\Classes\Yoga;
use DB;
class CredentialController extends Controller
{
    public function index(){
        $credentials = Credential::all();
        return view('credentials.index', compact(
            'credentials'
        ));
    }
    public function create(){
        return view('credentials.create');
    }
    public function edit($id){
        $credential = Credential::find($id);
        return view('credentials.edit', compact('credential'));
    }
    public function store(Request $request){
        dd( Input::all() );
        if ($this->valid( Input::all() )) {
            return $this->valid( Input::all() );
        }
        $credential = new Credential;
        $credential = $this->processData($credential);

        $pesan = Yoga::suksesFlash('Credential baru berhasil dibuat');
        return redirect('credentials')->withPesan($pesan);
    }
    public function update($id, Request $request){
        if ($this->valid( Input::all() )) {
            return $this->valid( Input::all() );
        }
        $credential = Credential::find($id);
        $credential = $this->processData($credential);

        $pesan = Yoga::suksesFlash('Credential berhasil diupdate');
        return redirect('credentials')->withPesan($pesan);
    }
    public function destroy($id){
        Credential::destroy($id);
        $pesan = Yoga::suksesFlash('Credential berhasil dihapus');
        return redirect('credentials')->withPesan($pesan);
    }

    public function processData($credential){
        dd( 'processData belum diatur' );
        $credential = $this->credential;
        $credential->save();

        return $credential;
    }
    public function import(){
        return 'Not Yet Handled';
        // run artisan : php artisan make:import CredentialImport 
        // di dalam file import :
        // use App\Models\Credential;
        // use Illuminate\Support\Collection;
        // use Maatwebsite\Excel\Concerns\ToCollection;
        // use Maatwebsite\Excel\Concerns\WithHeadingRow;
        // class CredentialImport implements ToCollection, WithHeadingRow
        // {
        // 
        //     public function collection(Collection $rows)
        //     {
        //         return $rows;
        //     }
        // }

        $rows = Excel::toArray(new CredentialImport, Input::file('file'))[0];
        $credentials     = [];
        $timestamp = date('Y-m-d H:i:s');
        foreach ($results as $result) {
            $credentials[] = [

                // Do insert here

                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ];
        }
        Credential::insert($credentials);
        $pesan = Yoga::suksesFlash('Import Data Berhasil');
        return redirect()->back()->withPesan($pesan);
    }
    private function valid( $data ){
        dd( 'validasi belum diatur' );
        $messages = [
            'required' => ':attribute Harus Diisi',
        ];
        $rules = [
            'data'           => 'required',
        ];
        $validator = \Validator::make($data, $rules, $messages);
        
        if ($validator->fails())
        {
            return \Redirect::back()->withErrors($validator)->withInput();
        }
    }
}
