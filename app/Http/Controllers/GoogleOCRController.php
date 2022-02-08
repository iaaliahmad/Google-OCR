<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Google\Cloud\Vision\VisionClient;
use Google\Auth\CredentialsLoader;

class GoogleOCRController extends Controller
{
    
    public function index(){
        return view('index');
    }
    
    public function parser(Request $request){

        $request->validate([
            'file' => 'required',
            'api_type' => 'required',
        ]);

        if($request->api_type == 'Vision API'){
            $request->validate([
                'file' => 'mimes:jpeg,png,jpg'
            ]);
        }
        elseif($request->type == 'Document AI'){
            $request->validate([
                'file' => 'mimes:jpeg,png,jpg,pdf'
            ]);
        }

        $file = $request->file('file');
        $api_type = $request->api_type;

        if($api_type == 'Vision API'){
            $response = $this->googleVisionAPI($file);
        }
        
        if($api_type == 'Document AI'){
            $response = $this->googleDocumentAi($file);
        }

        dd($response);

    }

    private function getGoogleAccessToken(){

        $scope = 'https://www.googleapis.com/auth/cloud-platform';
        $credentials = CredentialsLoader::makeCredentials($scope, json_decode(file_get_contents(
            base_path("google-service-key.json")
        ), true));

        $authToken = $credentials->fetchAuthToken();

        return $authToken['access_token'];
    }

    private function googleDocumentAi($file){

        ## GENERATE BASE64 CODE OF FILE 
        $base64File = base64_encode(file_get_contents($file));
        $mimeType = $file->getMimeType();

        ## GENERATE GOOGLE ACCESS TOKEN
        $accessToken = $this->getGoogleAccessToken();

        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('GOOGLE_PARSER_END_POINT'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "skipHumanReview": true,
                "inlineDocument": {
                    "mimeType": "'.$mimeType.'",
                    "content": "'.$base64File.'"
                }
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$accessToken.'',
                'Content-Type: application/json'
            ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        $response = json_decode($response,true);

        return $response;
    }

    private function googleVisionAPI($file){

        $vision = new VisionClient(['keyFile' => json_decode(file_get_contents((base_path("google-service-key.json"))), true)]);

        $imageResource = fopen($file, 'r');
        $image = $vision->image($imageResource, [ 'TEXT_DETECTION']);
        $annotation = $vision->annotate($image);

        $response = $annotation;
        // $response = $annotation->labels();
        // $response = $annotation->fullText();
        // $response = $annotation->info();


        return $response;
    }
    
}
