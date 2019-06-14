<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Http\Controllers\Controller; 

class PodRecyclingLocationController extends Controller 
{
	public $successStatus = 200;// OK
	/** 
     * ValidatePostcode api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function ValidatePostcode(Request $request){ 
		dd($request);
        $client = new Client(['base_uri' => 'https://api.postcodes.io/postcodes/']);

		$res = $client->request('POST', request->postcode .'/validate');

		if ($res->getStatusCode() == successStatus) { 
			$response_data = $res->getBody()->getContents();
			return response()->json(['success' => $response_data], $this-> successStatus); 
		}
		else{ 
            return response()->json(['error'=>'Unauthorised'], $res->getStatusCode()); 
        }
    }
	/** 
     * Register api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function register(Request $request) 
    { 
        
    }
	/** 
     * details api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function details() 
    { 
        
    } 
}