<?php
defined('BASEPATH') OR exit('No direct script access allowed');
define('useSeyfertTest' , true);
/**
 * SEYFERT API관련 모음
 * 실사용전 useSeyfertTest 를 FALSE로 세팅을 하여야 한다.
 * siteinfo config 참조함
**/
class seyfertapi {

  var $seyfertinfo;
  var $CI;
  var $perurl;
  function __construct() {
      $this->CI =& get_instance();
      $this->CI->load->database();
      $this->seyfertinfo = $this->CI->config->item('siteinfo');
      date_default_timezone_set('Asia/Seoul');
      if(useSeyfertTest){
        /*테스트서버사용시*/
        $this->preurl = "https://stg5.paygate.net";
        $this->seyfertinfo['reqMemGuid'] = "QfjZ7Byq2nJgy327qt8MGr";
        $this->seyfertinfo['reqMemKey'] = "p2PTq1g29zKryLNwUMHAdX1X9VHAjqs58F9QvLFd6Iu87omxUaKKR5yfd5gVgjaP";
      }else {
        $this->preurl = "https://v5.paygate.net";
      }
  }

  /**
   * UNIQ KEY 로 멤버생성.
   * 멤버생성시 email, 이름, 전화번호가 저장되지 않기때문에 생성후 기본정보를 UPDATE시켜줘야 함
   * @param zn_member.mem_idx
   * @return array *code,msg,data* (성공시 code=200)
   * @see   updateinfo()
  **/
  public function creatememberusekey( $mem_idx ) {
    $member = $this->CI->db->get_where('zn_member', array('mem_idx'=>(int)$mem_idx))->row_array();
    if( !isset($member['mem_idx']) || $member['mem_idx'] != $mem_idx ){
      return array("code"=>404, "msg"=>"회원정보를 찾을 수 없습니다.");
    }
    $uniq = $this->uniqkey($mem_idx);

    $url = "/v5a/member/createMember";
    $_method = "POST";
    $this->refid('cr');

    $ENCODE_PARAMS ="&_method=".$_method."&desc=desc&_lang=ko&reqMemGuid=".$this->seyfertinfo['reqMemGuid']."&nonce=".$this->seyfertinfo['nonce']
                  ."&merchantGuid=".$uniq ."&keyTp=GUID"
                  ."&fullname=".urlencode($member['mem_name'])."&nmLangCd=ko"
                  ."&emailAddrss=".urlencode($member['member_email'])."&emailTp=PERSONAL";
    if( $member['member_phone'] != '' ) $ENCODE_PARAMS .= "&phoneNo=".$member['member_phone']."&phoneCntryCd=KOR&phoneTp=MOBILE";
    list($res, $data) = $this->getres($_method, $url, $ENCODE_PARAMS );

    if( !$res ) return  array('code'=>500 ,'msg'=>'GATE Connection Error','error'=> $data) ;
    else if ( $data['status'] != 'SUCCESS') return array('code'=>410 ,'msg'=>"ERROR OCCURED", 'data'=>$data);
    else {
      if( $this->CI->db->update('zn_member', array('reqMemGuid'=>$data['data']['memGuid'])) ) return array('code'=>200 ,'msg'=>'SUCCESS', 'data'=>$data) ;
      else return array('code'=>400 ,'msg'=>'DB UPDATE ERROR', 'data'=>$data) ;
    }
  }

  /**
   * 멤버 기본정보 변경.
   * @param zn_member.mem_idx
   * @return array *code,msg,data* 성공시 code=200
  **/
  public function updateinfo($mem_idx) {
    $member = $this->CI->db->get_where('zn_member', array('mem_idx'=>(int)$mem_idx))->row_array();
    if( !isset($member['mem_idx']) || $member['mem_idx'] != $mem_idx ){
      return array("code"=>404, "msg"=>"회원정보를 찾을 수 없습니다.");
    }
    $url="/v5a/member/allInfo";
    $_method = "PUT";
    $this->refid('u');

    $ENCODE_PARAMS ="&_method=".$_method."&desc=desc&_lang=ko&reqMemGuid=".$this->seyfertinfo['reqMemGuid']."&nonce=".$this->seyfertinfo['nonce']
                  ."&dstMemGuid=".$member['reqMemGuid']."&phoneNo=".$member['member_phone']."&phoneCntryCd=KOR&phoneTp=MOBILE"
                  ."&fullname=".urlencode($member['mem_name'])."&nmLangCd=ko"
                  ."&emailAddrss=".urlencode($member['member_email'])."&emailTp=PERSONAL";
    list($res, $data) = $this->getres($_method, $url, $ENCODE_PARAMS );
    if( !$res ) return  array('code'=>500 ,'msg'=>'GATE Connection Error','error'=> $data) ;
    else if ( $data['status'] != 'SUCCESS') return array('code'=>410 ,'msg'=>"ERROR OCCURED", 'data'=>$data);
    else {
      return array('code'=>200 ,'msg'=>'SUCCESS', 'data'=>$data) ;
    }
  }
  /**
   * 잔액조회.
   * @param zn_member.mem_idx
   * @return array *code,msg,data* 성공시 code=200
  **/
  public function displayBalance($mem_idx) {
    $url = "/v5/member/seyfert/inquiry/balance";
    $_method = "POST";
    $this->refid('bal');
    $s_memGuid = $this->getmemguid($mem_idx);
    if ($s_memGuid===false) return array("code"=>404, "msg"=>"멤버키를 찾을 수 없습니다.");

    $ENCODE_PARAMS ="&_method=".$_method."&desc=desc&_lang=ko&reqMemGuid=".$this->seyfertinfo['reqMemGuid']."&nonce=".$this->seyfertinfo['nonce']."&refId=".$this->seyfertinfo['refid']
                    ."&dstMemGuid=".$s_memGuid."&crrncy=KRW";
    list($res, $data) = $this->getres($_method, $url, $ENCODE_PARAMS );

    if( !$res ) return  array('code'=>500 ,'msg'=>'GATE Connection Error','error'=> $data) ;
    else if ( $data['status'] != 'SUCCESS') return array('code'=>410 ,'msg'=>"ERROR OCCURED", 'data'=>$data);
    else {
      $amount = isset( $data['data']["moneyPair"]["amount"] ) ? (int) $data['data']["moneyPair"]["amount"] : '0';
      return array('code'=>200 ,'msg'=>'SUCCESS', 'data'=>array('amount'=>$amount,'han'=>$this->getConvertNumberToKorean($amount), 'data'=>$data) ) ;
    }
  }






  private function uniqkey($mem_idx) {
    $this->CI->load->helper('string');
    $uniq= random_string('md5');
    $row = $this->CI->db->select('mem_idx')->where('mem_key', $uniq)->limit(1)->get('zn_member')->row_array();
    if( !isset($row['mem_idx']) ){
      $this->CI->db->set('mem_key',$uniq)->update('zn_member');
      return $uniq;
    }else {
     return $this->uniqkey($mem_idx);
    }
  }




/* 기존 */
/* ID 잔액조회 */
  /* Pending 취소 */
  public function canceltid($tid,$refid=''){
    $url="/v5/transaction/seyfertTransferPending/cancel";
    $_method = "POST";
    $this->refid('c');
    $order = $this->CI->db->query("select * from mari_seyfert_order where s_tid = ? ", array($tid) )->row_array();
    $refid = isset($order['s_refId'])&& trim($order['s_refId'])!='' ? $order['s_refId'] : $refid;
    $i_subject = isset($order['i_subject'])&& trim($order['i_subject'])!='' ? $order['i_subject'] : '결제취소';
    $ENCODE_PARAMS="&_method=$_method&desc=desc&_lang=ko&reqMemGuid=".$this->seyfertinfo['reqMemGuid']."&nonce=".$this->seyfertinfo['nonce']."&title=".urlencode($i_subject)."&refId=".$refid."&authType=SMS_MO&timeout=30&parentTid=".$tid;
    list($res, $data) = $this->getres($_method, $url, $ENCODE_PARAMS );
    if( !$res ) return  array('code'=>500 ,'msg'=>'GATE Connection Error','error'=> $data) ;
    else if ( $data['status'] != 'SUCCESS') return array('code'=>410 ,'msg'=>"ERROR OCCURED", 'data'=>$data);
    else {
      return array('code'=>200 ,'msg'=>'SUCCESS', 'data'=>$data) ;
    }
  }

//========================================================================================================================
public function getmemguid($mem_idx){

  if($mem_idx == 'kfunding'){
    return $this->seyfertinfo['reqMemGuid'];
  }
  $bankck = $this->CI->db->get_where('zn_member', array('mem_idx'=>(int)$mem_idx))->row_array();
  if ( !isset( $bankck['reqMemGuid']) ) return false;
  else return $bankck['reqMemGuid'];
}
protected function refid($pre='rnd'){
  $this->seyfertinfo['nonce'] = $pre. time() . rand(111, 999);
  $this->seyfertinfo['refid'] = $pre."_r" . time() . rand(111, 999);
}
/* seyfert 통신 */
protected function getres($_method,$url, $ENCODE_PARAMS ){
  $cipher = AesCtr::encrypt($ENCODE_PARAMS, $this->seyfertinfo['reqMemKey'], 256);
  $cipherEncoded = urlencode($cipher);
  $requestString = "_method=".$_method."&reqMemGuid=".$this->seyfertinfo['reqMemGuid']."&encReq=".$cipherEncoded;
  $requestPath = $this->preurl.$url."?".$requestString;
  $curl_handlebank = curl_init();
  curl_setopt($curl_handlebank, CURLOPT_URL, $requestPath);
  /*curl_setopt($curl_handle, CURLOPT_ENCODING, 'UTF-8');*/
  curl_setopt($curl_handlebank, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($curl_handlebank, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl_handlebank, CURLOPT_SSL_VERIFYPEER, 0); //CURLOPT_SSL_VERIFYPEER is needed for https request.
  curl_setopt($curl_handlebank, CURLOPT_USERAGENT, $url);
  $result = curl_exec($curl_handlebank);
  if( $result===false ) $curlerror = curl_error($curl_handlebank);
  curl_close($curl_handlebank);
  $decode = json_decode($result, true);
  if( !is_array( $decode) )   return array(false, $curlerror);
  else return array(true, $decode);
}
protected function preauthcodemap($code){
  if($code=='PRE_AUTH_REG_FINISHED'){
    return array('code'=>200, 'msg'=>'인증완료.');
  }
  if($code=='REQUEST_HAS_TIME_OUT') {
    return array('code'=>400, 'msg'=>'요청시간경과.');//return false; //요청시간 경과
  }
  if($code=='PRE_AUTH_REG_TRYING'){
    return array('code'=>203, 'msg'=>'문자수신대기중');
  }
  if($code=='PRE_AUTH_REG_DEREGED_SELF'){
    return array('code'=>400, 'msg'=>'문자로 해지요청.');//return false; //문자로 해지
  }
  if($code =='PRE_AUTH_REG_DEREGED'){
    return array('code'=>400, 'msg'=>'선인증해지.');//return false; //문자로 해지
  }
  else return array('code'=>400, 'msg'=>'구분없음');
}
  public function code($code=''){
      $tcode =array('ARS'=>'ARS', 'ASSIGN_VACCNT'=>'가상계좌 발급', 'CHECK_BNK_CD'=>'은행 계좌주 코드 검증', 'CHECK_BNK_NM'=>'은행 계좌주 이름 검증', 'ESCROW_RELEASE'=>'에스크로 해제', 'EXCHANGE_MONEY'=>'환전', 'MO'=>'문자 질의 응답', 'PENDING_RELEASE'=>'세이퍼트 펜딩 해제', 'SEND_MONEY'=>'세이퍼트 송금', 'SEYFERT_PAYIN_VACCNT'=>'세이퍼트 가상계좌 입금 충전', 'SEYFERT_PAYIN_VACCNT_KYC'=>'KYC 집금', 'SEYFERT_RESERVED_PENDING'=>'세이퍼트 펜딩 예약 이체', 'SEYFERT_TRANSFER'=>'세이퍼트 에스크로 이체', 'SEYFERT_TRANSFER_PND'=>'세이퍼트 펜딩 이체', 'SEYFERT_TRANSFER_RESERVED'=>'deprecated', 'SEYFERT_TRANSFER_RSRV'=>'세이퍼트 예약 이체', 'SEYFERT_WITHDRAW'=>'세이퍼트 출금', 'SMS_API'=>'SMS', 'TRNSCTN_RECURRING'=>'세이퍼트 자동 결제', 'UNLIMITED_RESERVE'=>'무한 예약 이체', 'ARS'=>'ARS', 'ASSIGN_VACCNT'=>'가상계좌 발급', 'CHECK_BNK_CD'=>'은행 계좌주 코드 검증', 'CHECK_BNK_NM'=>'은행 계좌주 이름 검증', 'ESCROW_RELEASE'=>'에스크로 해제', 'EXCHANGE_MONEY'=>'환전', 'MO'=>'문자 질의 응답', 'PENDING_RELEASE'=>'세이퍼트 펜딩 해제', 'SEND_MONEY'=>'세이퍼트 송금', 'SEYFERT_PAYIN_VACCNT'=>'세이퍼트 가상계좌 입금 충전', 'SEYFERT_PAYIN_VACCNT_KYC'=>'KYC 집금', 'SEYFERT_RESERVED_PENDING'=>'세이퍼트 펜딩 예약 이체', 'SEYFERT_TRANSFER'=>'세이퍼트 에스크로 이체', 'SEYFERT_TRANSFER_PND'=>'세이퍼트 펜딩 이체', 'SEYFERT_TRANSFER_RESERVED'=>'deprecated', 'SEYFERT_TRANSFER_RSRV'=>'세이퍼트 예약 이체', 'SEYFERT_WITHDRAW'=>'세이퍼트 출금', 'SMS_API'=>'SMS', 'TRNSCTN_RECURRING'=>'세이퍼트 자동 결제', 'UNLIMITED_RESERVE'=>'무한 예약 이체',);
      $scode= array(
 'ACQUIRING_BANK_AGREEMENT'=> '세이퍼트 출금 동의',  'AGREE_FORCED_BY_MERCHANT'=> '세이퍼트 펜딩 이체 완료 (낮은 금액에 대한 미인증 이체) ',  'ARS_DENIED'=> 'ARS 인증 실패 ',  'ARS_FINISHED'=> 'ARS 인증 완료',  'ARS_INIT'=> 'ARS 인증 시작',  'ARS_TRYING'=> 'ARS 인증 고객 응답 대기',  'ASSIGN_VACCNT_FINISHED'=> '가상계좌 할당 성공',  'ASSIGN_VACCNT_INIT'=> '가상계좌 할당 시작',  'BANK_DEPOSIT_PAYIN_FINISHED'=> '은행 입금 입력 완료',  'BANK_DEPOSIT_PAYIN_INIT'=> '은행 입금 입력 시작 ',  'BANK_DEPOSIT_PAYOUT_FINISHED'=> '은행 출금 입력 완료 ',  'BANK_DEPOSIT_PAYOUT_INIT'=> '은행 출금 입력 시작 ',  'BANK_TRAN_ROLL_BACK'=> '세이퍼트 출금 실패에 따른 롤백',  'BANK_TRAN_ROLL_BACK_PASSBOOK'=> '세이퍼트 출금 실패에 따른 롤백',  'BATCH_RCRR_CANCELED'=> '자동이체 취소',  'BATCH_RCRR_ENOUGH_MONEY'=> '자동이체 2일전 잔고 충분 통보',  'BATCH_RCRR_INIT'=> '자동이체 초기화 ',  'BATCH_RCRR_NOTI_MONEY'=> '자동이체 2일전 잔고 부족 통보 ',  'BATCH_RCRR_RUN_DONE'=> '자동이체 완료 ',  'BATCH_RCRR_RUN_FAILED'=> '자동이체 실패',  'BATCH_RCRR_TRYING'=> '자동이체 진행 중',  'BATCH_UNLIMITED_RSRV_CANCELED'=> '무한 예약 이체 취소',  'BATCH_UNLIMITED_RSRV_TRYING'=> '무한 예약이체 처리 중 ',  'CHECK_BNK_ACCNT_FINISHED'=> '예금주 조회 완료',  'CHECK_BNK_EXISTANCE_CHECKED'=> '실계좌 확인 완료',  'CHECK_BNK_CD_FINISHED'=> '예금주 코드 검증 완료',  'CHECK_BNK_CD_INIT'=> '예금주 코드 검증 초기화 ',  'CHECK_BNK_NM_DENIED'=> '예금주명 조회 거절 ',  'CHECK_BNK_NM_FINISHED'=> '예금주명 조회 완료',  'CHECK_BNK_NM_INIT'=> '예금주명 조회 초기화',  'ESCROW_CANCELED'=> '에스크로 취소 ',  'ESCR_RELEASE_CANCELED'=> '에스크로 해제 취소',  'ESCR_RELEASE_CHLD_FINISHED'=> '에스크로 이체 원거래 해제됨',  'ESCR_RELEASE_FINISHED'=> '에스크로 해제 요청 완료',  'ESCR_RELEASE_REQ_APPROVED'=> '에스크로 해제 요청 승인',  'ESCR_RELEASE_REQ_AUTO_DONE'=> '에스크로 해제 자동 완료 ',  'ESCR_RELEASE_REQ_AUTO_ERROR'=> '에스크로 해제 자동 완료 에러',  'ESCR_RELEASE_REQ_AUTO_FINISHED'=> '에스크로 해제 요청 자동 완료',  'ESCR_RELEASE_REQ_AUTO_PROCESS'=> '에스크로 해제 자동 완료 진행 ',  'ESCR_RELEASE_REQ_CANCELED'=> '에스크로 해제 요청 취소',  'ESCR_RELEASE_REQ_DENIED'=> '에스크로 해제 요청 거부',  'ESCR_RELEASE_REQ_FINISHED'=> '에스크로 해제 요청 완료',  'ESCR_RELEASE_REQ_HOLD'=>'에스크로 해제 요청 완료',  'ESCR_RELEASE_REQ_INIT'=>'에스크로 해제 요청 시작',  'ESCR_RELEASE_REQ_TRYING'=>'에스크로 해제 요청 승인 시작',  'ESCR_RELEASE_REQ_TRY_FAILED'=>'에스크로 해제 요청 실패',  'EXCHANGE_MONEY_DENIED'=>'환전 거절',  'EXCHANGE_MONEY_FINISHED'=>'환전 완료',  'EXCHANGE_MONEY_INIT'=>'환전 시작',  'MO_DENIED'=>'MO 요청 거절',  'MO_DONE'=>'MO 요청 완료',  'MO_FINISHED'=>'MO 요청 완료',  'MO_INIT'=>'MO 요청 초기화',  'MO_TRYING'=>'MO 요청 진행중',  'NOT_ENOUGH_BAL_TO_PAY_FEE'=>'거래 실패 (충전금 부족)',  'NOT_VRFY_BNK_CD_KYC'=>'NOT_VRFY_BNK_CD_KYC',  'PAYIN_VACCNT_KYC_ACTIVATED'=>'KYC 가상계좌 입금 활성화',  'PAYIN_VACCNT_KYC_FAILED'=>'KYC 가상계좌 입금 실패',  'PAYIN_VACCNT_KYC_FINISHED'=>'KYC 가상계좌 입금 완료',  'PAYIN_VACCNT_KYC_INIT'=>'KYC 가상계좌 입금 초기화',  'PAYIN_VACCNT_KYC_REQ_TRYING'=>'KYC 가상계좌 입금 요청',  'PAYIN_VACCNT_KYC_SENDING_1WON'=>'KYC 가상계좌 입금 코드 전송',  'REG_RCRR_EXP_DT'=>'등록 중 최초거래보다 경과됨',  'REG_RCRR_INIT'=>'자동이체 등록 초기화',  'REG_RCRR_PARENT_CANCELED'=>'자동이체 부모 거래가 취소',  'REG_RCRR_REQ_FINISHED'=>'자동이체 등록 인증 완료',  'REG_RCRR_REQ_TRYING'=>'자동이체 등록 인증 진행 중',  'REG_RCRR_REQ_TRY_FAILED'=>'자동이체 등록 인증 실패',  'REQUEST_HAS_TIME_OUT'=>'요청 시간 경과',  'SEND_MONEY_FAILED'=>'송금 실패',  'SEND_MONEY_FINISHED'=>'송금 완료',  'SEND_MONEY_INIT'=>'송금 초기화',  'SEND_MONEY_ROLL_BACK'=>'송금 완료 후 은행 거절 반환 (입금 불능)',  'SEND_SMS_BNK_CD_FAILED'=>'SMS 수신 코드 매칭 실패',  'SFRT_PAYIN_RSRV_MATCHED'=>'세이퍼트 예약 입금 완료',  'SFRT_PAYIN_VACCNT_FAILED'=>'세이퍼트 입금 실패',  'SFRT_PAYIN_VACCNT_FINISHED'=>'세이퍼트 입금 완료',  'SFRT_PAYIN_VACCNT_INIT'=>'세이퍼트 입금 시작',  'SFRT_RSRV_PND_INIT'=>'예약 펜딩 거래 시작',  'SFRT_RSRV_PND_TRYING'=>'예약 펜딩 거래 고객 승인 대기',  'SFRT_TRNSFR_CANCELED'=>'세이퍼트 이체 취소',  'SFRT_TRNSFR_CHLD_CANCELED'=>'세이퍼트 펜딩 원거래 취소됨',  'SFRT_TRNSFR_ESCR_AUTO_DONE'=>'에스크로 해제 자동 완료',  'SFRT_TRNSFR_ESCR_DONE'=>'에스크로 해제 완료',  'SFRT_TRNSFR_FINISHED'=>'세이퍼트 이체 완료',  'SFRT_TRNSFR_INIT'=>'세이퍼트 이체 시작',  'SFRT_TRNSFR_PND_AGRREED'=>'세이퍼트 펜딩 거래 동의',  'SFRT_TRNSFR_PND_CANCELED'=>'세이퍼트 펜딩 거래 취소',  'SFRT_TRNSFR_PND_CHLD_RELEASED'=>'세이퍼트 펜딩 원거래 해제됨',  'SFRT_TRNSFR_PND_INIT'=>'세이퍼트 펜딩 거래 초기화',  'SFRT_TRNSFR_PND_RELEASED'=>'세이퍼트 펜딩 거래 해제',  'SFRT_TRNSFR_PND_RELEASE_INIT'=>'세이퍼트 펜딩 거래 해제 초기화',  'SFRT_TRNSFR_PND_TRYING'=>'세이퍼트 펜딩 거래 요청 중',  'SFRT_TRNSFR_REQ_APPROVED'=>'세이퍼트 이체 요청 승인',  'SFRT_TRNSFR_REQ_CANCELED'=>'세이퍼트 이체 요청 취소',  'SFRT_TRNSFR_REQ_DENIED'=>'세이퍼트 이체 요청 거부',  'SFRT_TRNSFR_REQ_FINISHED'=>'세이퍼트 이체 요청 완료',  'SFRT_TRNSFR_REQ_INIT'=>'세이퍼트 이체 요청 시작',  'SFRT_TRNSFR_REQ_TRYING'=>'세이퍼트 이체 요청 승인 처리중',  'SFRT_TRNSFR_REQ_TRY_FAILED'=>'세이퍼트 이체 요청 실패',  'SFRT_TRNSFR_RSRV_EXPIRED'=>'세이퍼트 예약입금이체 입금 시간 경과',  'SFRT_TRNSFR_RSRV_FINISHED'=>'세이퍼트 예약입금이체 실패',  'SFRT_TRNSFR_RSRV_INIT'=>'세이퍼트 예약입금이체 초기화',  'SFRT_TRNSFR_RSRV_MATCHED'=>'세이퍼트 예약입금이체 매칭',  'SFRT_TRNSFR_RSRV_TRYING'=>'세이퍼트 예약입금이체 진행 중',  'SFRT_WITHDRAW_CANCELED'=>'세이퍼트 출금 취소',  'SFRT_WITHDRAW_FAILED'=>'세이퍼트 출금 실패',  'SFRT_WITHDRAW_FINISHED'=>'세이퍼트 출금 완료',  'SFRT_WITHDRAW_FINISH_BNK_CD'=>'세이퍼트 출금 중 계좌주 코드 검증',  'SFRT_WITHDRAW_FINISH_BNK_NM'=>'세이퍼트 출금 중 예금주 이름 검증',  'SFRT_WITHDRAW_INIT'=>'세이퍼트 출금 시작',  'SFRT_WITHDRAW_MONEY_REQUSTED'=>'세이퍼트 출금 처리 전송됨',  'SFRT_WITHDRAW_REQ_TRYING'=>'세이퍼트 출금 요청 진행 중',  'SMS_API_FINISHED'=>'SMS API 완료',  'SMS_API_INIT'=>'SMS API 초기화',  'UNLIMITED_RESERVE_CANCELED'=>'무한 예약 이체 취소',  'UNLIMITED_RESERVE_INIT'=>'무한 예약 이체 초기화',  'UNLIMITED_RESERVE_MATCHED'=>'무한 예약 이체 매치',  'UNLIMITED_RESERVE_RUNNING'=>'무한 예약 이체 실행중',  'VRFY_BNK_CD_COUNT_EXCEED'=>'예금주 권한 검증을 위한 1원 송금 10회 초과',  'VRFY_BNK_CD_DONE'=>'예금주 권한 검증 완료',  'VRFY_BNK_CD_REQ_TRYING'=>'예금주 권한 검증 1원 송금 후 문자 발송',  'VRFY_BNK_CD_SENDING_1WON'=>'예금주 권한 검증을 위한 1원 송금',  'VRFY_BNK_NM_DONE'=>'예금주 이름 검증 완료',  'VRFY_BNK_NM_REQ_TRYING'=>'예금중 이름 검증 진행 중','ASSIGN_VACCNT_UNASSIGNED'=>'가상계좌해지','BNK_NM_NEED_REVIEW'=>'계좌주이름확인팰요'
    );

    return ( isset($scode[$code]) )? $scode[$code]."(".$code.")" :  ( ( isset( $tcode[$code]) ) ? $tcode[$code]."(".$code.")" : $code ) ;
  }
  protected function getConvertNumberToKorean($_number)
  {
    if($_number=='') return "0";
  	$number_arr = array('','일','이','삼','사','오','육','칠','팔','구');
  	$unit_arr1 = array('','십','백','천');
  	$unit_arr2 = array('','만','억','조','경','해');
  	$result = array();
  	$reverse_arr = str_split(strrev($_number), 4);
    $result_idx =0;
  	foreach($reverse_arr as $reverse_idx=>$reverse_number){
  		$convert_arr = str_split($reverse_number);
  		$convert_idx =0;
  		foreach($convert_arr as $split_idx=>$split_number){
  			if(!empty($number_arr[$split_number])){
  				$result[$result_idx] = $number_arr[$split_number].$unit_arr1[$split_idx];
  				if(empty($convert_idx)) $result[$result_idx] .= $unit_arr2[$reverse_idx];
  				++$convert_idx;
  			}
  			++$result_idx;
  		}
  	}
  	$result = implode('', array_reverse($result));
  	return $result;
  }

}
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
/*  AES implementation in PHP                                                                     */
/*    (c) Chris Veness 2005-2011 www.movable-type.co.uk/scripts                                   */
/*    Right of free use is granted for all commercial or non-commercial use providing this        */
/*    copyright notice is retainded. No warranty of any form is offered.                          */
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

class Aes {

  /**
   * AES Cipher function: encrypt 'input' with Rijndael algorithm
   *
   * @param input message as byte-array (16 bytes)
   * @param w     key schedule as 2D byte-array (Nr+1 x Nb bytes) -
   *              generated from the cipher key by keyExpansion()
   * @return      ciphertext as byte-array (16 bytes)
   */
  public static function cipher($input, $w) {    // main cipher function [§5.1]
    $Nb = 4;                 // block size (in words): no of columns in state (fixed at 4 for AES)
    $Nr = count($w)/$Nb - 1; // no of rounds: 10/12/14 for 128/192/256-bit keys

    $state = array();  // initialise 4xNb byte-array 'state' with input [§3.4]
    for ($i=0; $i<4*$Nb; $i++) $state[$i%4][floor($i/4)] = $input[$i];

    $state = self::addRoundKey($state, $w, 0, $Nb);

    for ($round=1; $round<$Nr; $round++) {  // apply Nr rounds
      $state = self::subBytes($state, $Nb);
      $state = self::shiftRows($state, $Nb);
      $state = self::mixColumns($state, $Nb);
      $state = self::addRoundKey($state, $w, $round, $Nb);
    }

    $state = self::subBytes($state, $Nb);
    $state = self::shiftRows($state, $Nb);
    $state = self::addRoundKey($state, $w, $Nr, $Nb);

    $output = array(4*$Nb);  // convert state to 1-d array before returning [§3.4]
    for ($i=0; $i<4*$Nb; $i++) $output[$i] = $state[$i%4][floor($i/4)];
    return $output;
  }


  private static function addRoundKey($state, $w, $rnd, $Nb) {  // xor Round Key into state S [§5.1.4]
    for ($r=0; $r<4; $r++) {
      for ($c=0; $c<$Nb; $c++) $state[$r][$c] ^= $w[$rnd*4+$c][$r];
    }
    return $state;
  }

  private static function subBytes($s, $Nb) {    // apply SBox to state S [§5.1.1]
    for ($r=0; $r<4; $r++) {
      for ($c=0; $c<$Nb; $c++) $s[$r][$c] = self::$sBox[$s[$r][$c]];
    }
    return $s;
  }

  private static function shiftRows($s, $Nb) {    // shift row r of state S left by r bytes [§5.1.2]
    $t = array(4);
    for ($r=1; $r<4; $r++) {
      for ($c=0; $c<4; $c++) $t[$c] = $s[$r][($c+$r)%$Nb];  // shift into temp copy
      for ($c=0; $c<4; $c++) $s[$r][$c] = $t[$c];           // and copy back
    }          // note that this will work for Nb=4,5,6, but not 7,8 (always 4 for AES):
    return $s;  // see fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.311.pdf
  }

  private static function mixColumns($s, $Nb) {   // combine bytes of each col of state S [§5.1.3]
    for ($c=0; $c<4; $c++) {
      $a = array(4);  // 'a' is a copy of the current column from 's'
      $b = array(4);  // 'b' is a•{02} in GF(2^8)
      for ($i=0; $i<4; $i++) {
        $a[$i] = $s[$i][$c];
        $b[$i] = $s[$i][$c]&0x80 ? $s[$i][$c]<<1 ^ 0x011b : $s[$i][$c]<<1;
      }
      // a[n] ^ b[n] is a•{03} in GF(2^8)
      $s[0][$c] = $b[0] ^ $a[1] ^ $b[1] ^ $a[2] ^ $a[3]; // 2*a0 + 3*a1 + a2 + a3
      $s[1][$c] = $a[0] ^ $b[1] ^ $a[2] ^ $b[2] ^ $a[3]; // a0 * 2*a1 + 3*a2 + a3
      $s[2][$c] = $a[0] ^ $a[1] ^ $b[2] ^ $a[3] ^ $b[3]; // a0 + a1 + 2*a2 + 3*a3
      $s[3][$c] = $a[0] ^ $b[0] ^ $a[1] ^ $a[2] ^ $b[3]; // 3*a0 + a1 + a2 + 2*a3
    }
    return $s;
  }

  /**
   * Key expansion for Rijndael cipher(): performs key expansion on cipher key
   * to generate a key schedule
   *
   * @param key cipher key byte-array (16 bytes)
   * @return    key schedule as 2D byte-array (Nr+1 x Nb bytes)
   */
  public static function keyExpansion($key) {  // generate Key Schedule from Cipher Key [§5.2]
    $Nb = 4;              // block size (in words): no of columns in state (fixed at 4 for AES)
    $Nk = count($key)/4;  // key length (in words): 4/6/8 for 128/192/256-bit keys
    $Nr = $Nk + 6;        // no of rounds: 10/12/14 for 128/192/256-bit keys

    $w = array();
    $temp = array();

    for ($i=0; $i<$Nk; $i++) {
      $r = array($key[4*$i], $key[4*$i+1], $key[4*$i+2], $key[4*$i+3]);
      $w[$i] = $r;
    }

    for ($i=$Nk; $i<($Nb*($Nr+1)); $i++) {
      $w[$i] = array();
      for ($t=0; $t<4; $t++) $temp[$t] = $w[$i-1][$t];
      if ($i % $Nk == 0) {
        $temp = self::subWord(self::rotWord($temp));
        for ($t=0; $t<4; $t++) $temp[$t] ^= self::$rCon[$i/$Nk][$t];
      } else if ($Nk > 6 && $i%$Nk == 4) {
        $temp = self::subWord($temp);
      }
      for ($t=0; $t<4; $t++) $w[$i][$t] = $w[$i-$Nk][$t] ^ $temp[$t];
    }
    return $w;
  }

  private static function subWord($w) {    // apply SBox to 4-byte word w
    for ($i=0; $i<4; $i++) $w[$i] = self::$sBox[$w[$i]];
    return $w;
  }

  private static function rotWord($w) {    // rotate 4-byte word w left by one byte
    $tmp = $w[0];
    for ($i=0; $i<3; $i++) $w[$i] = $w[$i+1];
    $w[3] = $tmp;
    return $w;
  }

  // sBox is pre-computed multiplicative inverse in GF(2^8) used in subBytes and keyExpansion [§5.1.1]
  private static $sBox = array(
    0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
    0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
    0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
    0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
    0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
    0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
    0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
    0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
    0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
    0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
    0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
    0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
    0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
    0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
    0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
    0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16);

  // rCon is Round Constant used for the Key Expansion [1st col is 2^(r-1) in GF(2^8)] [§5.2]
  private static $rCon = array(
    array(0x00, 0x00, 0x00, 0x00),
    array(0x01, 0x00, 0x00, 0x00),
    array(0x02, 0x00, 0x00, 0x00),
    array(0x04, 0x00, 0x00, 0x00),
    array(0x08, 0x00, 0x00, 0x00),
    array(0x10, 0x00, 0x00, 0x00),
    array(0x20, 0x00, 0x00, 0x00),
    array(0x40, 0x00, 0x00, 0x00),
    array(0x80, 0x00, 0x00, 0x00),
    array(0x1b, 0x00, 0x00, 0x00),
    array(0x36, 0x00, 0x00, 0x00) );

}

/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
/*  AES counter (CTR) mode implementation in PHP                                                  */
/*    (c) Chris Veness 2005-2011 www.movable-type.co.uk/scripts                                   */
/*    Right of free use is granted for all commercial or non-commercial use providing this        */
/*    copyright notice is retainded. No warranty of any form is offered.                          */
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

class AesCtr extends Aes {

  /**
   * Encrypt a text using AES encryption in Counter mode of operation
   *  - see http://csrc.nist.gov/publications/nistpubs/800-38a/sp800-38a.pdf
   *
   * Unicode multi-byte character safe
   *
   * @param plaintext source text to be encrypted
   * @param password  the password to use to generate a key
   * @param nBits     number of bits to be used in the key (128, 192, or 256)
   * @return          encrypted text
   */
  public static function encrypt($plaintext, $password, $nBits) {
    $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
    if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
    // note PHP (5) gives us plaintext and password in UTF8 encoding!

    // use AES itself to encrypt password to get cipher key (using plain password as source for
    // key expansion) - gives us well encrypted key
    $nBytes = $nBits/8;  // no bytes in key
    $pwBytes = array();
    for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
    $key = Aes::cipher($pwBytes, Aes::keyExpansion($pwBytes));
    $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long

    // initialise 1st 8 bytes of counter block with nonce (NIST SP800-38A §B.2): [0-1] = millisec,
    // [2-3] = random, [4-7] = seconds, giving guaranteed sub-ms uniqueness up to Feb 2106
    $counterBlock = array();
    $nonce = floor(microtime(true)*1000);   // timestamp: milliseconds since 1-Jan-1970
    $nonceMs = $nonce%1000;
    $nonceSec = floor($nonce/1000);
    $nonceRnd = floor(rand(0, 0xffff));

    for ($i=0; $i<2; $i++) $counterBlock[$i]   = self::urs($nonceMs,  $i*8) & 0xff;
    for ($i=0; $i<2; $i++) $counterBlock[$i+2] = self::urs($nonceRnd, $i*8) & 0xff;
    for ($i=0; $i<4; $i++) $counterBlock[$i+4] = self::urs($nonceSec, $i*8) & 0xff;

    // and convert it to a string to go on the front of the ciphertext
    $ctrTxt = '';
    for ($i=0; $i<8; $i++) $ctrTxt .= chr($counterBlock[$i]);

    // generate key schedule - an expansion of the key into distinct Key Rounds for each round
    $keySchedule = Aes::keyExpansion($key);
    //print_r($keySchedule);

    $blockCount = ceil(strlen($plaintext)/$blockSize);
    $ciphertxt = array();  // ciphertext as array of strings

    for ($b=0; $b<$blockCount; $b++) {
      // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
      // done in two stages for 32-bit ops: using two words allows us to go past 2^32 blocks (68GB)
      for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
      for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs($b/0x100000000, $c*8);

      $cipherCntr = Aes::cipher($counterBlock, $keySchedule);  // -- encrypt counter block --

      // block size is reduced on final block
      $blockLength = $b<$blockCount-1 ? $blockSize : (strlen($plaintext)-1)%$blockSize+1;
      $cipherByte = array();

      for ($i=0; $i<$blockLength; $i++) {  // -- xor plaintext with ciphered counter byte-by-byte --
        $cipherByte[$i] = $cipherCntr[$i] ^ ord(substr($plaintext, $b*$blockSize+$i, 1));
        $cipherByte[$i] = chr($cipherByte[$i]);
      }
      $ciphertxt[$b] = implode('', $cipherByte);  // escape troublesome characters in ciphertext
    }

    // implode is more efficient than repeated string concatenation
    $ciphertext = $ctrTxt . implode('', $ciphertxt);
    $ciphertext = base64_encode($ciphertext);
    return $ciphertext;
  }


  /**
   * Decrypt a text encrypted by AES in counter mode of operation
   *
   * @param ciphertext source text to be decrypted
   * @param password   the password to use to generate a key
   * @param nBits      number of bits to be used in the key (128, 192, or 256)
   * @return           decrypted text
   */
  public static function decrypt($ciphertext, $password, $nBits) {
    $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
    if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
    $ciphertext = base64_decode($ciphertext);

    // use AES to encrypt password (mirroring encrypt routine)
    $nBytes = $nBits/8;  // no bytes in key
    $pwBytes = array();
    for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
    $key = Aes::cipher($pwBytes, Aes::keyExpansion($pwBytes));
    $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long

    // recover nonce from 1st element of ciphertext
    $counterBlock = array();
    $ctrTxt = substr($ciphertext, 0, 8);
    for ($i=0; $i<8; $i++) $counterBlock[$i] = ord(substr($ctrTxt,$i,1));

    // generate key schedule
    $keySchedule = Aes::keyExpansion($key);

    // separate ciphertext into blocks (skipping past initial 8 bytes)
    $nBlocks = ceil((strlen($ciphertext)-8) / $blockSize);
    $ct = array();
    for ($b=0; $b<$nBlocks; $b++) $ct[$b] = substr($ciphertext, 8+$b*$blockSize, 16);
    $ciphertext = $ct;  // ciphertext is now array of block-length strings

    // plaintext will get generated block-by-block into array of block-length strings
    $plaintxt = array();

    for ($b=0; $b<$nBlocks; $b++) {
      // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
      for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
      for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs(($b+1)/0x100000000-1, $c*8) & 0xff;

      $cipherCntr = Aes::cipher($counterBlock, $keySchedule);  // encrypt counter block

      $plaintxtByte = array();
      for ($i=0; $i<strlen($ciphertext[$b]); $i++) {
        // -- xor plaintext with ciphered counter byte-by-byte --
        $plaintxtByte[$i] = $cipherCntr[$i] ^ ord(substr($ciphertext[$b],$i,1));
        $plaintxtByte[$i] = chr($plaintxtByte[$i]);

      }
      $plaintxt[$b] = implode('', $plaintxtByte);
    }

    // join array of blocks into single plaintext string
    $plaintext = implode('',$plaintxt);

    return $plaintext;
  }


  /*
   * Unsigned right shift function, since PHP has neither >>> operator nor unsigned ints
   *
   * @param a  number to be shifted (32-bit integer)
   * @param b  number of bits to shift a to the right (0..31)
   * @return   a right-shifted and zero-filled by b bits
   */
  private static function urs($a, $b) {
    $a &= 0xffffffff; $b &= 0x1f;  // (bounds check)
    if ($a&0x80000000 && $b>0) {   // if left-most bit set
      $a = ($a>>1) & 0x7fffffff;   //   right-shift one bit & clear left-most bit
      $a = $a >> ($b-1);           //   remaining right-shifts
    } else {                       // otherwise
      $a = ($a>>$b);               //   use normal right-shift
    }
    return $a;
  }

}
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
