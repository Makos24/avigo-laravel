<?php

namespace App\Http\Controllers;

use App\Models\Birth_contact;
use App\Models\Flagged_Registration;
use App\Models\Name;
use App\Models\Parent_detail;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index()
    {
        return ['pwa' => 'PWA controller'];
    }

    public function upload(Request $request)
    {
        //return response()->json($request->all());
        $test = array();
        foreach($request->all() as $item){
            //return ['msg' => $item];
           $p = Parent_detail::firstOrNew([
               'phone' => $this->cleanPhone($item['phone'])
               ]
            );

            if($p->exists){

                if($e = $p->edd()->where('mother', $item['mother'])->latest()->first()){
                    $datetime1 = new DateTime($e->edd);

                    $datetime2 = new DateTime($item['edd']);
        
                    $difference = $datetime2->diff($datetime1);
                if($difference->format("%a") > 140){
                    $this->addEdd($item, $p);
                }else{
                    Flagged_Registration::create([
                        'name' => $item['name'],
                        'settlement_id' => $item['settlement'],
                        'ward_id' => $item['ward'],
                        'lga_id' => $item['lga'],
                        'state_id' => $item['state'],
                        'phone' => $item['phone'],
                        'phc_id' => $item['phc'],
                        'edd' => $item['edd'],
                        'mother' => $item['mother'],
                    ]);
                }

            }

            }else{
                $p->name = $item['name'];
                $p->phone = $this->cleanPhone($item['phone']);
                $p->state_id = $item['state'];
                $p->lga_id = $item['lga'];
                $p->ward_id = $item['ward'];
                $p->settlement_id = $item['settlement'];
                $p->phc_id = $item['phc'];
     
                $p->save();

                $this->addEdd($item, $p);
            }
            
          
           $test = $p;
        }

         return ['msg' => 'Upload successful.'];
    }


    public function addEdd($item, $p)
    {
        $mother = Name::firstOrCreate(['name' => $item['name']]);
                    
        $e = $p->create()->edd([
            'edd' => $item['edd'],
            'phc_id' => $item['phc'],
            'mother' => $mother->id,
            'date' => date('Y-m-d h:i:s'),
        ]);

        Birth_contact::insert(
            ['phone' => $this->cleanPhone($item['phone1'])],
            ['phone' => $this->cleanPhone($item['phone2'])],
            ['phone' => $this->cleanPhone($item['phone3'])],
        );

        $p->parent_notifications()->create([
            'edd_id' => $e->id,
            'parent_id' => $p->id,
            'phone' => $this->cleanPhone($item['phone']),
            'mother' => $mother->id,
        ]);

        $e->birth_gender()->create();
    
    }

    public function cleanPhone($number)
{
    if(substr($number, 0, 1) == "+"){
        return str_replace(' ', '', $number);
    }elseif(substr($number, 0, 1) == "2"){
        return "+".str_replace(' ', '', $number);
    }elseif(substr($number, 0, 1) == "0"){
            $p = ltrim($number, '0');
        
            return '+234'.str_replace(' ', '',$p);
    }
}

}
