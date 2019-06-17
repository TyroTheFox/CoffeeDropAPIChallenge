<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request; 
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Http\Controllers\Controller; 
use Illuminate\Support\Facades\Validator;
use App\User; 
use Illuminate\Support\Facades\Auth; 

class PodRecyclingLocationController extends Controller 
{
	public $successStatus = 200;// OK
	
	private $earthRadius = 6371;  // earth radius in km
	private $postcodeURI = 'https://api.postcodes.io/postcodes/';
	
	//Capsule Return Cashback Amount Rules
	private $capsuleRuleTier1 = ['tierStart' => 0, 'tierEnd' => 50, 'Ristretto' => 2, 'Espresso' => 4, 'Lungo' => 6];
	private $capsuleRuleTier2 = ['tierStart' => 50, 'tierEnd' => 500, 'Ristretto' => 3, 'Espresso' => 6, 'Lungo' => 9];
	private $capsuleRuleTier3 = ['tierStart' => 501, 'tierEnd' => -1, 'Ristretto' => 5, 'Espresso' => 10, 'Lungo' => 15];
	
	/** 
     * ValidatePostcode api 
     * Checks that a postcode is valid
     * @return \Illuminate\Http\Response 
     */ 
    public function ValidatePostcode(Request $request){ 
		//Validates that the 'postcode' is of the correct form
		$validator = Validator::make($request->all(), [
            'postcode' => 
				array(
					'required',
					'regex:/^([A-Za-z][A-Ha-hK-Yk-y]?[0-9][A-Za-z0-9]? ?[0-9][A-Za-z]{2}|[Gg][Ii][Rr] ?0[Aa]{2})$/m'
				)
        ]);

        if ($validator->fails()) {
			return response()->json(['error'=>'Invalid Postcode'], $this->successStatus); 
        }
		//Then checks that the postcode is valid against the postcode.io API
        $client = new Client(['base_uri' => $this->postcodeURI]);

		$res = $client->request('GET', $request->postcode .'/validate');

		if ($res->getStatusCode() == $this->successStatus) { 
			$response_data = $res->getBody()->getContents();
			//If all good, return
			return response()->json(['success' => $response_data], $this->successStatus); 
		}
		else{ 
            return response()->json(['error'=>'Trouble Reaching Server'], $res->getStatusCode()); 
        }
    }
	/** 
     * GetNearestLocation api 
     * Returns the nearlest location from the postcode database to a given postcode
     * @return \Illuminate\Http\Response 
     */ 
    public function GetNearestLocation(Request $request) 
    { 
		//Check postcode is valid
		if($this->CheckPostcode($request)){
			//Attempt to opt out early and search DB for exactMatches
			$exactMatches = DB::select('select * from tbl_name where postcode = ?', [$request->postcode]);
			if(count($exactMatches) >= 0){
				//Request Postcode is a DB Location, return that info
			};
			
			//If not exact location, narrow down possibilities
			$requestPoint = $this->GetLatLongForPostcode($request->postcode);
			if($requestPoint == false){return response()->json(['error'=>'Postcode Server Error'], $this->successStatus);}
			$outcode = array();
			//Get outcode for postcode
			if(preg_match('/^([A-Z]\d{1,2}|[A-Z]{2}\d(\d|[A-Z])?)( \d[A-Z]{2})?$/mi', $request->postcode, $outcode)){	
				//Find matches with similar outcode
				$matches = DB::select('select * from tbl_name where postcode like ?', [$outcode[1].'%']);
				//Find closest match
				$shortestDistance = 0.0;
				if(count($matches) > 0){
					//Find postcode with shortest distance to request postcode
					$shortestMatch = $this->FindShortestDistanceToRequestPostcode($requestPoint, $matches);
					//Return shortest match's information to the user
					$openingHours = $this->BuildLocationTimesResponse($shortestMatch);
					$address = $this->BuildLocationAddressResponce($shortestMatch->postcode);
					
					return response()->json(['address' => $address, 'openingHours' => $openingHours], $this->successStatus);
				}else{
					//Attempt to find closest Postcode to Request Postcode
					$DBPostcodes = DB::select('select * from tbl_name');
					$shortestPostcode = $this->FindShortestDistanceToRequestPostcode($requestPoint, $DBPostcodes);
					//Return shortest match's information to the user
					$openingHours = $this->BuildLocationTimesResponse($shortestPostcode);
					$address = $this->BuildLocationAddressResponce($shortestPostcode->postcode);
					
					return response()->json(['address' => $address, 'openingHours' => $openingHours], $this->successStatus);
				}
			}
		
		}else{
			return response()->json(['error'=>'Invalid Postcode'], $this->successStatus); 
		}
    }
	
	/** 
     * CreateNewLocation api 
     * Adds a new location to the postcode table
     * @return \Illuminate\Http\Response 
     */ 
    public function CreateNewLocation(Request $request) 
    {
		//Decode incomming JSON
		$content = json_decode($request->getContent());
		//Authenticate user with password and email, can be user token if needs be but seems simpler for this test to use this set up for now
		if(Auth::attempt(['email' => request('email'), 'password' => request('password')])){ 
            $user = Auth::user(); 
			//Check for a postcode
			if(!empty($content->postcode)){
				//Vet the new postcode to ensure it works
				$request = new Request([
					'postcode'   => $content->postcode
				]);
				if(!$this->CheckPostcode($request)){ return response()->json(['error'=>'Postcode Invalid'], 200); }
				if(!DB::connection()->getDatabaseName()){ return response()->json(['error'=>'Database Not Found'], 200); }
				$result = DB::select('select * from tbl_name where postcode = :postcode', ['postcode' => $content->postcode]);
				if(count($result) > 0){return response()->json(['error'=>'Postcode Already Exists'], 200);}
				//Create the database statement
				DB::insert('insert into tbl_name (
							postcode, 
							open_Monday, open_Tuesday, open_Wednesday, open_Thursday, open_Friday, open_Saturday, open_Sunday, 
							closed_Monday, closed_Tuesday, closed_Wednesday, closed_Thursday, closed_Friday, closed_Saturday, closed_Sunday) 
							values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
							[
								$content->postcode, 
								(!empty($content->opening_times->monday		) ? $content->opening_times->monday		: ""),
								(!empty($content->opening_times->tuesday	) ? $content->opening_times->tuesday	: ""),
								(!empty($content->opening_times->wednesday	) ? $content->opening_times->wednesday	: ""),
								(!empty($content->opening_times->thursday	) ? $content->opening_times->thursday	: ""),
								(!empty($content->opening_times->friday		) ? $content->opening_times->friday		: ""),
								(!empty($content->opening_times->saturday	) ? $content->opening_times->saturday	: ""),
								(!empty($content->opening_times->sunday		) ? $content->opening_times->sunday		: ""),
								(!empty($content->closing_times->monday		) ? $content->closing_times->monday		: ""),
								(!empty($content->closing_times->tuesday	) ? $content->closing_times->tuesday	: ""),
								(!empty($content->closing_times->wednesday	) ? $content->closing_times->wednesday	: ""),
								(!empty($content->closing_times->thursday	) ? $content->closing_times->thursday	: ""),
								(!empty($content->closing_times->friday		) ? $content->closing_times->friday		: ""),
								(!empty($content->closing_times->saturday	) ? $content->closing_times->saturday	: ""),
								(!empty($content->closing_times->sunday		) ? $content->closing_times->sunday		: "")
							]);
				//Send responce
				return response()->json(['success'=>'AddressAdded'], 200); 
			}else{
				return response()->json(['error'=>'Postcode Needed'], 200); 
			}
        } 
        else{ 
            return response()->json(['error'=>'Unauthorised'], 401); 
        } 
	}
	
	/** 
     * CalculateCashback api 
     * Calculates how much cashback a user receives for their coffeepods according to three given tiers
     * @return \Illuminate\Http\Response 
     */ 
    public function CalculateCashback(Request $request) 
    {
		//Decode incomming JSON
		$content = json_decode($request->getContent());
		//Authenticate user with password and email, can be user token if needs be but seems simpler for this test to use this set up for now
		if(Auth::attempt(['email' => request('email'), 'password' => request('password')])){ 
            $user = Auth::user(); 
			$finalTotal = 0;
			$tempPodcount = 0;
			
			$tierOne = 0;
			$tierTwo = 0;
			$tierThree = 0;
			
			$tierOneCount = 0;
			$tierTwoCount = 0;
			$tierThreeCount = 0;
			
			$finalResponce = array();
			
			foreach($content as $coffee => $podcount){
				//If count is Zero, end
				if($podcount > 0){
					//If count is less than the tier, record amount and continue
					if($this->capsuleRuleTier1['tierEnd'] >= $podcount){
						$finalTotal += $podcount * $this->capsuleRuleTier1[$coffee];
						$tierOne = $podcount * $this->capsuleRuleTier1[$coffee];
						$tierOneCount = $podcount;
					}else{
						//If greater than tier 1, record max for tier and record how many pods are left
						$finalTotal = $this->capsuleRuleTier1['tierEnd'] * $this->capsuleRuleTier1[$coffee];
						$tempPodcount = $podcount - $this->capsuleRuleTier1['tierEnd'];
						$tierOne = $this->capsuleRuleTier1['tierEnd'] * $this->capsuleRuleTier1[$coffee];
						$tierOneCount = $this->capsuleRuleTier1['tierEnd'];
						//If podcount is less than the extent of tier 2, record amound and continue
						if($this->capsuleRuleTier2['tierEnd'] >= $podcount){
							$finalTotal += $tempPodcount * $this->capsuleRuleTier2[$coffee];
							$tierTwo = $tempPodcount * $this->capsuleRuleTier2[$coffee];
							$tierTwoCount = $tempPodcount;
						}else{
							//If greater than tier 2, record max for tier and record how many pods are left
							$tierTwoCount = $this->capsuleRuleTier2['tierEnd'] - $this->capsuleRuleTier1['tierEnd'];
							$finalTotal += $tierTwoCount * $this->capsuleRuleTier2[$coffee];
							$tempPodcount = $podcount - $this->capsuleRuleTier2['tierEnd'];
							$tierTwo = $tierTwoCount * $this->capsuleRuleTier2[$coffee];
							
							//Record rest of the pod total with Tier 3 rules
							$finalTotal += $tempPodcount * $this->capsuleRuleTier3[$coffee];
							$tierThree = $tempPodcount * $this->capsuleRuleTier3[$coffee];
							$tierThreeCount = $tempPodcount;
						}
					}
					//Create final counts of everything
					$finalResponce[$coffee] = [ 
						'tier1Total' => $tierOne, 
						'tier2Total' => $tierTwo, 
						'tier3Total' => $tierThree,
						'tier1Count' => $tierOneCount,
						'tier2Count' => $tierTwoCount,
						'tier3Count' => $tierThreeCount,
						'Total'		 => $finalTotal,
						'Count'		 => $tierOneCount + $tierTwoCount + $tierThreeCount
					];
				}
				//Reset
				$tierOne = 0;
				$tierTwo = 0;
				$tierThree = 0;
				
				$tierOneCount = 0;
				$tierTwoCount = 0;
				$tierThreeCount = 0;
				$finalTotal = 0;
				$tempPodcount = 0;
			}
			if(count($finalResponce) == 0){ return response()->json(['error'=> 'No Pods Submitted'], 200); }
			//Find requested information
			$fullCount = 0;
			$fullTotal = 0;
			foreach($finalResponce as $coffee => $responce){
				$fullCount += $finalResponce[$coffee]['Count'];
				$fullTotal += $finalResponce[$coffee]['Total'];
			}
			//Change to pounds and pence
			$fullTotal = round($fullTotal / 100, 2, PHP_ROUND_HALF_UP);
			//Create final reciept
			$reciept = ['Count' =>  $fullCount, 'Total' => $fullTotal, 'ItemList' => $finalResponce];
			$this->AddReceiptToDatabase($reciept);
			//Return requested information
			return response()->json(['success'=> [ 'Count' =>  $fullCount, 'Total' => $fullTotal] ], 200); 
		}
	}
	
	/** 
     * GetLastFiveReciepts api 
     * Returns the last five coffee pod return reciepts from the database
     * @return \Illuminate\Http\Response 
     */ 
    public function GetLastFiveReciepts(Request $request) 
    {
		if(Auth::attempt(['email' => request('email'), 'password' => request('password')])){ 
			$user = DB::select('select * from users where email = :email', ['email' => $request['userEmail']]);
			$reciepts = DB::select('select * from receipts where userID = :userID limit 5', ['userID' => $user[0]->id]);
			foreach($reciepts as $reciept){
				$receiptItems = array();
				$itemIDs = json_decode($reciept->itemIDs);
				foreach($itemIDs as $itemID){
					$items = DB::select('select * from receiptitems where id = :id', ['id' => $itemID]);
					array_push($receiptItems, $items[0]);
				}
				$reciept->items = $receiptItems;
			}
			
			$responce = array(
								"user" => [
											"id" => $user[0]->id, 
											"name" => $user[0]->name, 
											"email" => $user[0]->email
											],
								"reciepts" => $reciepts
							); 
			return response()->json(['success'=> $responce ], 200); 
		}
		else{ 
            return response()->json(['error'=>'Unauthorised'], 401); 
        }
	}
	
	/** 
     * AddReceiptToDatabase api 
     * Adds a Reciept to the database
     */ 
	private function AddReceiptToDatabase($reciept){
		$ids = array();
		//Add items first to get their IDs, then reciepts that contains ids for all the items
		//Not ideal to be accessing the database directly like this when an object interface is better long term
		//But it's a simple test done in a weekend. More time spent could make for a better system here. 
		//Also, this might be more heav-duty than is strictly needed as I could just store JSON blobs but I figure
		//it might be more usable by other immaginary departments. The marketing team might want a HUD of this data
		//or the unicorns in accounting might need to know how much is being paid out to whom.
		if (DB::connection()->getDatabaseName())
		{
			foreach($reciept['ItemList'] as $coffee => $item){
				$id = DB::table('receiptitems')->insertGetId(
					[ 
						'coffeepodType' => $coffee,
						'tier1Total' 	=> $item['tier1Total'],
						'tier2Total' 	=> $item['tier2Total'],
						'tier3Total' 	=> $item['tier3Total'],
						'tier1Count' 	=> $item['tier1Count'],
						'tier2Count' 	=> $item['tier2Count'],
						'tier3Count' 	=> $item['tier3Count'],
						'total'		 	=> $item['Total'],
						'count'		 	=> $item['Count']
					]
				);
				array_push($ids, $id);
			}
			DB::table('receipts')->insert(
				[ 
					'userID' 	=> Auth::user()->id,
					'itemIDs' 	=> json_encode($ids),
					'total' 	=> $reciept['Total'],
					'count' 	=> $reciept['Count']
				]
			);
		}
	}
	
	/** 
     * FindShortestDistanceToRequestPostcode api 
     * Looks for the shortest distance between a given postcode and a list of postcodes provided
     */ 
	private function FindShortestDistanceToRequestPostcode($requestPoint, $postcodeList){
		//Find postcode with shortest distance to request postcode
		$firstPoint = $this->GetLatLongForPostcode($postcodeList[0]->postcode);
		if($firstPoint == false){ return response()->json(['error'=>'Postcode Server Error'], $this->successStatus); }
		$shortestDistance = $this->GetDistanceBetweenTwoPoints($requestPoint, $firstPoint);
		$shortestCode = $postcodeList[0];
		unset($postcodeList[0]);
		foreach($postcodeList as $postcode){
			$point = $this->GetLatLongForPostcode($postcode->postcode);
			if($point == false){ return response()->json(['error'=>'Postcode Server Error'], $this->successStatus); }
			$distance = $this->GetDistanceBetweenTwoPoints($requestPoint, $point);		
			if($distance < $shortestDistance){
				$shortestDistance = $distance;
				$shortestCode = $postcode;
			}
		}
		return $shortestCode;
	}
	
	/** 
     * BuildLocationTimesResponse api 
     * Builds a location opening and closing hours responce
     */ 
	private function BuildLocationTimesResponse($location){
		$monday = [$location->open_Monday, $location->closed_Monday];
		$tuesday = [$location->open_Tuesday, $location->closed_Tuesday];
		$wednesday = [$location->open_Wednesday, $location->closed_Wednesday];
		$thursday = [$location->open_Thursday, $location->closed_Thursday];
		$friday = [$location->open_Friday, $location->closed_Friday];
		$saturday = [$location->open_Saturday, $location->closed_Saturday];
		$sunday = [$location->open_Sunday, $location->closed_Sunday];
		
		$openingHours = array(
							"monday" => $monday,
							"tuesday" => $tuesday,
							"wednesday" => $wednesday,
							"thursday" => $thursday,
							"friday" => $friday,
							"saturday" => $saturday,
							"sunday" => $sunday
						);
		
		return $openingHours;
	}
	
	/** 
     * BuildLocationAddressResponce api 
     * Builds a responce object for a location address
     */ 
	private function BuildLocationAddressResponce($postcode){
		$client = new Client(['base_uri' => $this->postcodeURI]);

		$res = $client->request('GET', $postcode);

		if ($res->getStatusCode() == $this->successStatus) { 
			$response_data = json_decode($res->getBody()->getContents());
			$resultData = $response_data->result;
			//Honestly not 100% certain on what's needed so I'm adding just what looks the most prudent
			$address = array(
								"postcode" => $resultData->postcode,
								"country" => $resultData->country,
								"region" => $resultData->region,
								"district" => $resultData->admin_district,
								"latitude" => $resultData->latitude,
								"longitude" => $resultData->longitude
							);
			
			return $address;
		}
		else{ 
            return false; 
        }
	}
	
	/** 
     * CheckPostcode api 
     * Checks a postcode against the postcode.io API to make sure it's valid
     */ 
	private function CheckPostcode(Request $request){
		$valResult = $this->ValidatePostcode($request);
		$resultJSON = json_decode($valResult->getContent());
		if(property_exists($resultJSON, "success")){
			$postcodeResponse = json_decode($resultJSON->success);
			if($postcodeResponse->result){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	
	/** 
     * GetLatLongForPostcode api 
     * Gets a longitude and latitude for a given postcode
     */ 
	private function GetLatLongForPostcode($postcode){
		$client = new Client(['base_uri' => $this->postcodeURI]);

		$res = $client->request('GET', $postcode);

		if ($res->getStatusCode() == $this->successStatus) { 
			$response_data = json_decode($res->getBody()->getContents());
			$resultData = $response_data->result;
			$point = array($resultData->latitude, $resultData->longitude);
			return $point; 
		}
		else{ 
            return false; 
        }
	}
	
	/** 
     * GetDistanceBetweenTwoPoints api 
     * Calculate a distance between two pairs of longitude and latitude upon a spherical earth
     */ 
	private function GetDistanceBetweenTwoPoints($point1 , $point2){
		// array of lat-long i.e  $point1 = [lat,long]
		
		$point1Lat = $point1[0];
		$point2Lat =$point2[0];
		$deltaLat = deg2rad($point2Lat - $point1Lat);
		$point1Long =$point1[1];
		$point2Long =$point2[1];
		$deltaLong = deg2rad($point2Long - $point1Long);
		$a = sin($deltaLat/2) * sin($deltaLat/2) + cos(deg2rad($point1Lat)) * cos(deg2rad($point2Lat)) * sin($deltaLong/2) * sin($deltaLong/2);
		$c = 2 * atan2(sqrt($a), sqrt(1-$a));

		$distance = $this->earthRadius * $c;
		return $distance;    // in km
	}
}