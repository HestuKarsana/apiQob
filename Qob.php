<?php

use Restserver\Libraries\REST_Controller;
require(APPPATH.'/libraries/REST_Controller.php');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: POST, OPTIONS");

class qob extends REST_Controller {
    function __construct(){
        parent::__construct();
        $this->load->helper('url');
        $this->db2 = $this->load->database('dbsqlsrvsales', TRUE);
        // $this->load->library('template');
    }

/*-----------------------Main Function--------------------*/
    //get data  for send to api faster/pickup 
	public function getAddPosting_post(){
		$userid = $this->post('userid');
		$sql		= "SELECT * from qobpos.dbo.qob_order where userId = ? AND status = '00' ORDER BY insert_time DESC";
		$query		= $this->db->query($sql, array($userid));
		if($query->num_rows() > 0){
			$list = $query->result_array();
			$this->response(array("result" => $list), 200);
		}else{
			$this->response(array('errors'=> array('global' => 'Not Found', 'userid' => $userid)), 400);
		}
	}

    public function getRequestPickup_post(){
        $userid = $this->post('userid');
        $sql = "SELECT a.pickup_number, count(b.externalId) as jumlahOrder, a.update_time, a.insert_time as pickupTime, a.fastername,
                a.shipper_latlong, a.faster_latlong, a.status
                FROM qobpos.dbo.history_pickup a 
                inner join qobpos.dbo.qob_order b on a.pickup_number = b.pickup_number
                WHERE b.userId = ?
                GROUP BY a.pickup_number, a.update_time, a.insert_time, a.fastername, a.shipper_latlong, a.faster_latlong
                ORDER BY a.insert_time DESC";
        $query  = $this->db->query($sql, array($userid));
        if($query->num_rows() > 0 ){
            $list    = $query->result_array();
            $this->response(array("result" => $list), 200);
        }else{
            $this->response(array('errors'=> array('global' => 'Not Found', 'userid' => $userid)), 400);
        }
    }

    //get postalcode from from order, etc.
	public function getPostalCode_post(){
		$kodepos	= $this->post('kodepos');
		$sql		= "SELECT * FROM db_order.dbo.ref_kodepos where kodepos = ? ";
		$query		= $this->db->query($sql, array($kodepos));
		if($query->num_rows() > 0){
			$list = $query->result_array();
			$this->response(array("result" => $list), 200);
		}else{
			$this->response(array('errors'=> array('global' => 'tidak ditemukan')), 400);
		}
	}

    //push token
    public function pushToken_post(){
        $token = $this->post('token');
        $userId = $this->post('userid');
        $curdate = $this->getCurdate();

        $data = array(
            'token' => $token,
            'userid' => $userId,
            'create_time' => $curdate
        );

        $this->db->insert('qobpos.dbo.qob_token', $data);
        if ($this->db->affected_rows() > 0) {
            $this->response(array('status' => 200), 200);
        }else{
            $this->response(array('status' => 400), 400);
        }
    }
	

    //if needs for push status from faster
	public function updatePickup_post(){
		//get pickupnumber
        $items 			= $this->post('externalId');
		$pickup_number	= $this->post('pickup_number');
        $shipper_latlong = $this->post('shipper_latlong');
		$curdate		= $this->getCurdate();
        $updateData 	= array();
        for ($i=0; $i < count($items); $i++) { 
            $updateData[] = array(
                'externalId' 	=> $items[$i],
                'status'	 	=> '01',
				'update_time' 	=> $curdate,
				'pickup_number'	=> $pickup_number
            );  
        }
    	
        $this->db->update_batch('qobpos.dbo.qob_order', $updateData, 'externalId');
        if ($this->db->affected_rows() > 0) {
            $toDoInsert         = array(
                'pickup_number'     => $pickup_number,
                'shipper_latlong'   => $shipper_latlong,
                'status' => '01'
            );
            $this->db->insert('qobpos.dbo.history_pickup', $toDoInsert);
            if($this->db->affected_rows() > 0){
                $this->response(200);
            }else{//gagal insert
                $this->response(array('errors' => array('global' => 'Failed to get pickup number')), 400);
            }
        }else{//gagal update
            $this->response(array('errors' => array('global' => 'Failed to get pickup number')), 400);
        }
    }


    //push for faster
    public function driverLoc_post(){
    	$pickup_number 	= $this->post('pickup_number');
    	$faster_latlong = $this->post('faster_latlong');
        $curdate        = $this->getCurdate();
        $toDoUpdate		= array(
        	// 'pickup_number' 	=> $pickup_number,
        	'faster_latlong'	=> $faster_latlong,
        	'fastername' 		=> $this->post('fastername'),
            'update_time'       => $curdate,
        );
        $this->db->where('pickup_number',$pickup_number);
        $this->db->update('qobpos.dbo.history_pickup', $toDoUpdate);
        if($this->db->affected_rows() > 0){
            $this->response(array('result' => array('Msg' => 'Success get location')), 200);
        }else{
            $this->response(array('errors' => array('global' => 'Failed to get your location, Please turn on GPS Location')), 400);
        }
    }

    //get tracking faster
    public function trackingFaster_post(){
        $pickup_number  = $this->post('pickup_number');
        $sql      		= "SELECT * from qobpos.dbo.history_pickup where pickup_number = ?";
        $query      	= $this->db->query($sql, array($pickup_number));
        if($query->num_rows() > 0){
            $tracking 	= $query->result_array();
            $this->response(array("result" => $tracking), 200);
        }else{
            $this->response(array('errors'=> array('global' => 'Not Found', 'pickup_number' => $pickup_number), 400));
        }
    }

    //push data to api  AddPosting and Internal database
    public function index_post(){
        $curdate    = $this->getCurdate();
    	$id_order	= $this->getIdorder();
    	// $id_order='QOB3'.rand(000000000,999999999);
    	$dataOrder	= array(
    			'userId'			=> $this->post('userId'),
    			'status'			=> '00',
    			'panjang'			=> $this->post('length'),
    			'lebar'				=> $this->post('width'),
    			'tinggi'			=> $this->post('height'),
    			'cod'				=> $this->post('cod'),
    			'externalId' 		=> $id_order,
    			'customerId' 		=> 'QOBMOBILE',
    			'serviceId' 		=> $this->post('serviceCode'),
    			'senderName'		=> $this->post('senderName'),
    			'senderAddr' 		=> $this->post('senderAddres'),
    			'senderVill' 		=> '-',
    			'senderSubDist' 	=> $this->post('senderKec'),
    			'senderCity' 		=> $this->post('senderCity'),
    			'senderProv' 		=> $this->post('senderProv'),
    			'senderCountry' 	=> 'Indonesia',
    			'senderPosCode' 	=> $this->post('senderPos'),
    			'senderEmail' 		=> $this->post('senderMail'),
    			'senderPhone' 		=> $this->post('senderPhone'),
    			'receiverName' 		=> $this->post('receiverName'),
    			'receiverAddr'		=> $this->post('receiverAddress'),
    			'receiverVill' 		=> $this->post('receiverVill'),
    			'receiverSubDist' 	=> $this->post('receiverKec'),
    			'receiverCity' 		=> $this->post('receiverKab'),
    			'receiverProv' 		=> $this->post('receiverProv'),
    			'receiverCountry' 	=> 'Indonesia',
    			'receiverPosCode' 	=> $this->post('receiverPos'),
    			'receiverEmail' 	=> $this->post('receiverMail'),
    			'receiverPhone' 	=> $this->post('receiverPhone'),
    			'orderDate' 		=> $curdate,
    			'weight' 			=> $this->post('berat'), // p x l x t
    			'fee' 				=> $this->post('fee'),
    			'feeTax' 			=> $this->post('feeTax'),
    			'insurance' 		=> $this->post('insurance'),
    			'insuranceTax' 		=> $this->post('insuranceTax'),
    			'itemValue' 		=> $this->post('itemValue'),
    			'contentDesc' 		=> $this->post('contentDesc'),
                'insert_time'       => $curdate
    		);
        $result = array();
        $this->db->insert('qobpos.dbo.qob_order', $dataOrder);
        if ($this->db->affected_rows() > 0) {
            $DataaddPosting = array(
                'userId'            => 'demo1',
                'password'          => 'demo1pass',
                'type'              => '-',
                'externalId'        => $id_order,
                'customerId'        => 'QOBMOBILE',
                'serviceId'         => $this->post('serviceCode'),
                'senderName'        => $this->post('senderName'),
                'senderAddr'        => $this->post('senderAddres'),
                'senderVill'        => '-',
                'senderSubDist'     => $this->post('senderKec'),
                'senderCity'        => $this->post('senderCity'),
                'senderProv'        => $this->post('senderProv'),
                'senderCountry'     => 'Indonesia',
                'senderPosCode'     => $this->post('senderPos'),
                'senderEmail'       => $this->post('senderMail'),
                'senderPhone'       => $this->post('senderPhone'),
                'receiverName'      => $this->post('receiverName'),
                'receiverAddr'      => $this->post('receiverAddress'),
                'receiverVill'      => $this->post('receiverVill'),
                'receiverSubDist'   => $this->post('receiverKec'),
                'receiverCity'      => $this->post('receiverKab'),
                'receiverProv'      => $this->post('receiverProv'),
                'receiverCountry'   => 'Indonesia',
                'receiverPosCode'   => $this->post('receiverPos'),
                'receiverEmail'     => $this->post('receiverMail'),
                'receiverPhone'     => $this->post('receiverPhone'),
                'orderDate'         => $curdate,
                'weight'            => $this->post('berat'), // p x l x t
                'fee'               => $this->post('fee'),
                'feeTax'            => $this->post('feeTax'),
                'insurance'         => $this->post('insurance'),
                'insuranceTax'      => $this->post('insuranceTax'),
                'itemValue'         => $this->post('itemValue'),
                'contentDesc'       => $this->post('contentDesc')
            );
            $send = $this->sendAddPosting($DataaddPosting);
            if ($send['success']) {
                $this->response(array('idOrder' => $id_order), 200);
            }else{
                $message = $send['message'];
                $this->response(array('errors' => array('global' => "Data gagal disimpan response status = $message")), 400);
                $sql = "DELETE FROM qobpos.dbo.qob_order WHERE externalId = ?";
                $qDelete = $this->db->query(array($sql, $id_order));
            }
        }else{
            $this->response(array('errors' => array('global' => "Data gagal disimpan")), 400);
        }
    }


	
/*-----------------------Other Function-----------------------------------------*/

	private function getIdorder(){
        $sql    = $this->db->query("SELECT TOP 1 RIGHT(externalId, 10) as id from qobpos.dbo.qob_order ORDER BY RIGHT(externalId, 10) DESC")->row_array();
        $id     = (int)$sql['id'] + 1;
        $id     = str_pad($id, 10, "0", STR_PAD_LEFT);
        $newId  = "QOB3".$id."";
        return $newId;
    }

	private function getCurdate(){
        $sql = "SELECT getdate() as sekarang";
        $now    = $this->db->query($sql)->row_array();
        $now    = $now['sekarang'];
        return $now;
    }

    private function sendAddPosting($arr){
        $result = array();
        ini_set('soap.wsdl_cache_enabled',0);
        ini_set('soap.wsdl_cache_ttl',0);
        try {
            $wsdl            = base_url('assets/PosWebServices-20161201-dev.wsdl.xml');
            $client          = new SoapClient($wsdl, array(
                'cache_wsdl' => WSDL_CACHE_NONE
            ));
            $response   = $client->addPosting($arr);
            $data       = $response;
            $data       = $response->r_posting;
            $respon     = $data->responseId;
            if ($respon == '000') {
                $result['success'] = true;
                $result['response'] = $respon;
            }else{
                $result['success'] = false;
                $result['response'] = $respon;
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['response'] = $respon;
        }
        return $result;
    }
}

?>