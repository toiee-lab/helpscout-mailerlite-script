<?php
	
/**
* HelpScout Integration App for Mailelite
*
* ver 0.1
*
*/

require_once( dirname( __FILE__ ).'/config.php' );



function isFromHelpScout($data, $signature) {
	$calculated = base64_encode(hash_hmac('sha1', $data, WEBHOOK_SECRET_KEY, true));
	return $signature == $calculated;
}

function getMailerliteInfo($email)
{
	// mailerlite api で通信
	require 'vendor/autoload.php';
	
	$mailerliteClient = new \MailerLiteApi\MailerLite( MAILERLITE_API_KEY );
	
	$subscribersApi = $mailerliteClient->subscribers();
	
	$subscriber = $subscribersApi->find( $email );

	if( is_null($subscriber) )
	{
		return "<h4>Mailerliteの登録がありません</h4>";
	}
		
	$groups = $subscribersApi->getGroups($subscriber->id);
	$act = $subscribersApi->getActivity($subscriber->id);	


	$fields = array();

	// subscribers data		
	$fields['id']         = $subscriber->id;
	$fields['sent']       = $subscriber->sent;
	$fields['opened']     = $subscriber->opened;
	$fields['open_ratio'] = $subscriber->opened_rate * 100;
	$fields['type']       = $subscriber->type;

	$tmparr = $subscriber->fields;
	foreach($tmparr as $v)
	{
		$fields[ $v->key ] = $v->value;
	}
	
	// group info
	$fields['mail-groups'] = array();
	foreach($groups as $o)
	{
		$fields['mail-groups'][] = $o->name;
	}

	// act info
	$fields['mail-act'] = array();
	foreach($act as $o)
	{
		$fields['mail-act'][] = array(
			'date'      => $o->date,
			'report_id' => $o->report_id,
			'subject'   => $o->subject,
			'type'      => $o->type
		);
	}
	
	// メールグループ登録一覧	
	$mags = '';
	foreach($fields['mail-groups'] as $mg_name)
	{
		$mags .= "<li>{$mg_name}</li>\n";
	}
	
	// アクティビティ一覧
	$act_list = '';
	foreach($fields['mail-act'] as $v)
	{
		$act_list .= "<li><span class=\"muted\">{$v['date']}</span> <a href=\"https://app.mailerlite.com/reports/view/{$v['report_id']}\">{$v['subject']}</a> {$v['type']}</li>\n";
	}
		
	// Output
	$html =<<<EOF
<h4><a href="https://app.mailerlite.com/subscribers/single/{$fields['id']}">{$fields['last_name']} {$fields['name']}</a></h4>
<p class="muted">送信数:{$fields['sent']}<br>開封数:{$fields['opened']}({$fields['open_ratio']}%)<br>状態:{$fields['type']}</p>
<div class="toggleGroup open">
    <h4><a href="#" class="toggleBtn"><i class="icon-person"></i>メール登録</a></h4>
    <div class="toggle indent">
        <ul>
        	{$mags}
        </ul>
    </div>
</div>
<div class="toggleGroup">
    <h4><a href="#" class="toggleBtn"><i class="icon-flag"></i>最近の受信履歴</a></h4>
    <div class="toggle indent">
        <ul class="unstyled">
            {$act_list}
        </ul>
    </div>
</div>
EOF;
	
	return $html;
}


// Main


// HelpScout からのアクセスかをチェックする
$signature = $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'];
$data = file_get_contents('php://input');

if ( isFromHelpScout($data, $signature) ) 
{
	
	$vals = json_decode( $data , true);
	$html = getMailerliteInfo( $vals['customer']['email'] );

	header("Content-Type: text/plain;");
	echo json_encode(array(
		'html' => $html
	));

}
else
{

	echo "Err: Invalid access";
	
}
