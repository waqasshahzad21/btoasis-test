<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;
use Predis\Client;

class ApiController extends BaseController
{
	public function __construct()
    {
		$this->api_base = "https://api.alternative.me/v2";
		$this->client = new Client('tcp://localhost:6379');
	}
	
	public function coins()
	{
		$data = [];
		$sort = isset($_GET['sort']) ? $_GET['sort'] : 'ASC';
		$cache_data = $this->client->get('coin_data');
		if($cache_data){
			$coins = unserialize($cache_data);
			$ret_data = [];
			if($sort == 'DESC')
				usort($coins, function ($a, $b){ return strtolower($b['name']) <=> strtolower($a['name']);});
			else
				usort($coins, function ($a, $b){ return strtolower($a['name']) <=> strtolower($b['name']);});
			foreach($coins as $coin){
				$ret_data [] = ['code' => $coin['code'], 'name' => $coin['name']];
			}
			$data = $ret_data;
		}else{
			$data = $this->handle_coins();
		}
		
		return Response::json($data, 200);
		
	}
	
	public function ticker($code = '')
	{
		$data = [];
		$id = '';
		$code = trim($code);
		if($code != ''){
			$cache_data = $this->client->get($code);
			//$coin = unserialize($cache_data);
			if($cache_data){
				$coin = unserialize($cache_data);
				//var_dump($coin);
				$id = $coin['id'];
				$ret_data = ['code' => $coin['code'], 'price' => $coin['price'], 'volume' => $coin['volume'],
							 'daily_change' => $coin['daily_change'], 'last_updated' => $coin['last_updated']];
				
				$data = $ret_data;
			}else{
				
				$cache_data = $this->client->get('JC_' . $code);
				$coin = [];
				if($cache_data){
					$coin = unserialize($cache_data);
					$id = $coin['id'];
				}else{
					$data = $this->handle_coins();
					$cache_data = $this->client->get('JC_' . $code);
					if($cache_data){
						$coin = unserialize($cache_data);
						$id = $coin['id'];
					}
				}
				
				if($id != '')
				{
					$endpoint = '/ticker/'.$id . '/';
					$return = $this->get_data($endpoint);
					//var_dump($return);
					
					if($return['errors']){
						$data = ['error' => '408', 'error_message' => 'Unable to connect to API endpoint', 'timestamp' => time()];
					}else{
						$req_data = json_decode($return['data'], true);
						if(isset($req_data['data'][$id])){			
							$cache_data = [
											'id' => $id,
											'code' => $req_data['data'][$id]['symbol'],
											'price' => $req_data['data'][$id]['quotes']['USD']['price'],
											'volume' => $req_data['data'][$id]['quotes']['USD']['volume_24h'],
											'daily_change' => $req_data['data'][$id]['quotes']['USD']['percentage_change_24h'],
											'last_updated' => time()
										];
							$this->client->set($req_data['data'][$id]['symbol'], serialize($cache_data));
							$this->client->expire($req_data['data'][$id]['symbol'], 300);
							
							$ret_data = $cache_data;
							unset($ret_data['id']);
							$ret_data['last_updated'] = $req_data['data'][$id]['last_updated'];
							$data = $ret_data;
						}else{
							$data = ['error' => '404', 'error_message' => 'Code not found', 'timestamp' => time()];
						}
					}
				}else{
					$data = ['error' => '400', 'error_message' => 'Code not found', 'timestamp' => time()];
				}
			}
		}else{
			$data = ['error' => '400', 'error_message' => 'Code is required', 'timestamp' => time()];
		}
		return Response::json($data, 200);
	}
	
	
	private function handle_coins()
	{
		$endpoint = '/listings/';
		$return = $this->get_data($endpoint);
		$data = [];
		if($return['errors']){
			$data = ['error' => '408', 'error_message' => 'Unable to connect to API endpoint', 'timestamp' => time()];
		}else{
			$data = $return['data'];
			$req_data = json_decode($return['data'], true);
			if(isset($req_data['data'])){
				$ret_data = [];
				$cache_data = [];
				foreach($req_data['data'] as $coin){
					$ret_data [] = ['code' => $coin['symbol'], 'name' => $coin['name']];
					$c_data = ['id' => $coin['id'], 'code' => $coin['symbol'], 'name' => $coin['name']];
					$cache_data [] = $c_data;
					
					$this->client->set('JC_' . $coin['symbol'], serialize($c_data));
				}
				$data = $ret_data;
				$this->client->set('coin_data', serialize($cache_data));
			}else{
				$data = ['error' => '408', 'error_message' => 'Unable to connect to API endpoint', 'timestamp' => time()];
			}
		}
		return $data;
	}
	
	private function get_data($endpoint)
	{
		$url  = $this->api_base . $endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
		if (curl_errno($ch)) {
			$error_msg = curl_error($ch);
		}
		
		curl_close($ch);
		
		$return = ['data' => $output, 'errors' => false, 'message' => ''];
		
		if(isset($error_msg)) {
			$return = ['data' => $output, 'errors' => true, 'message' => $error_msg];
		}
		
		return $return;
	}
}
