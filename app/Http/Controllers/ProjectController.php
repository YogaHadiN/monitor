<?php

namespace App\Http\Controllers;

use App\Models\Mdl;
use Illuminate\Http\Request;
use Input;
use PDF;
// use Excel;
// use App\Imports\MdlImport;
use App\Models\Classes\Yoga;
use DB;

class ProjectController extends Controller
{
    public function index(){
        $model_singulars = Mdl::all();
        return view('model_singulars.index', compact(
            'model_singulars'
        ));
    }
    public function create(){
        return view('model_singulars.create');
    }
    public function edit($id){
        $model_singular = Mdl::find($id);
        return view('model_singulars.edit', compact('model_singular'));
    }
    public function store(Request $request){
        dd( Input::all() );
        if ($this->valid( Input::all() )) {
            return $this->valid( Input::all() );
        }
        $model_singular = new Mdl;
        $model_singular = $this->processData($model_singular);

        $pesan = Yoga::suksesFlash('Mdl baru berhasil dibuat');
        return redirect('model_singulars')->withPesan($pesan);
    }
    public function update($id, Request $request){
        if ($this->valid( Input::all() )) {
            return $this->valid( Input::all() );
        }
        $model_singular = Mdl::find($id);
        $model_singular = $this->processData($model_singular);

        $pesan = Yoga::suksesFlash('Mdl berhasil diupdate');
        return redirect('model_singulars')->withPesan($pesan);
    }
    public function destroy($id){
        Mdl::destroy($id);
        $pesan = Yoga::suksesFlash('Mdl berhasil dihapus');
        return redirect('model_singulars')->withPesan($pesan);
    }

    public function processData($model_singular){
        dd( 'processData belum diatur' );
        $model_singular = $this->model_singular;
        $model_singular->save();

        return $model_singular;
    }
    public function import(){
        return 'Not Yet Handled';
        // run artisan : php artisan make:import MdlImport 
        // di dalam file import :
        // use App\Models\Mdl;
        // use Illuminate\Support\Collection;
        // use Maatwebsite\Excel\Concerns\ToCollection;
        // use Maatwebsite\Excel\Concerns\WithHeadingRow;
        // class MdlImport implements ToCollection, WithHeadingRow
        // {
        // 
        //     public function collection(Collection $rows)
        //     {
        //         return $rows;
        //     }
        // }

        $rows = Excel::toArray(new MdlImport, Input::file('file'))[0];
        $model_singulars     = [];
        $timestamp = date('Y-m-d H:i:s');
        foreach ($results as $result) {
            $model_singulars[] = [

                // Do insert here

                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ];
        }
        Mdl::insert($model_singulars);
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

    public function antrian_farmasi(){
        return view('projects.antrian_farmasi');
    }
    public function antrian_dokter(){
        return view('projects.antrian_dokter');
    }
    public function ambil_antrian(){
        return view('projects.ambil_antrian');
    }
    public function uang(){
        $pdf = PDF::loadView('projects.uang');
        $pdf->setOption('enable-local-file-access', true);
        return $pdf->stream();
    }
    public function general_concent(){
        $pdf = PDF::loadView('projects.general_concent');
        $pdf->setOption('enable-local-file-access', true);
        return $pdf->stream();
    }
}
