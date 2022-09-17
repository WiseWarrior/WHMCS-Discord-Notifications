<?php
///////////////////////// Provided For Free By ///////////////////////////
//	Version: 1.0a
//	Requires: /docroot/includes/hooks/discord_config.json
//	About:
//	Original by William Phillips - MetallicGloss.com
//	 - https://github.com/metallicgloss/WHMCS-Discord-Notifications
//	Advanced further by: Ben Picken - BigWetFish.hosting
//	 - https://github.com/WiseWarrior/WHMCS-Discord-Notifications.git

Class DiscordConfig {
	public $whmcsAdminURL;
	public $companyName;
	public $container;

	function __construct(string $index = null){
		try{
			$file = file_get_contents(dirname(__FILE__)."/discord_config.json");
			if ($file === false)
				throw new Exception("Failed to open discord config file: '".dirname(__FILE__)."/discord_config.json");

			$json = json_decode($file, true);
			if ($json === null)
				throw new Exception("Failed to phase discord config file.");

			$this->container = array();
			$g = &$json['globals'];

			$this->whmcsAdminURL = (isset($g['whmcsAdminURL']) ? $g['whmcsAdminURL'] : "");
			$this->companyName = (isset($g['companyName']) ? $g['companyName'] : "");

			$channels = &$json['channels'];

			foreach($channels as &$v){
				if (array_key_exists($index,$v)){
					$ch = $this->NewChannelEntry();
					$ch["discordWebHookURL"] = $v['discordWebHookURL'];
					$cf = &$ch['config'];
					foreach($v as $key => &$value){
						if(array_key_exists($key,$cf))
							$cf[$key] = $value;
					}
					$this->container += $ch;
				}
			}
			if(count($this->container) <= 0)
				$this->container = null;
		}catch(Exception $e){
			discord_log("[class :: DiscordConfig :: __construct] Error: $e");
		}
	}

	function NewChannelEntry(){
		return array(
			'discordWebHookURL'		=>	"",	// Your Discord WebHook URL.
			'discordColor'			=>	hexdec("#fff"),	// Discord Message Color. The color code format within this script is standard hex
			'discordGroupID'		=>	"",	// Discord Group ID Notification. An example of a group ID is: <@&343029528563548162>
			'discordWebHookAvatar'	=>	"",	// Discord Avatar Dynamic Image.
			'whmcsAdminURL'			=>	$this->whmcsAdminURL,
			'companyName'			=>	$this->companyName,
			'config'				=>	array(
				'ticketOpened'				=>	false,	// New Ticket Opened Notification
				'ticketUserReply'			=>	false,	// Ticket User Reply Received Notification
				'ticketFlagged'				=>	false,	// Ticket Flagged To Staff Member Notification
				'ticketNewNote'				=>	false,	// New Note Added To Ticket Notification
				'invoicePaid'				=>	false,	// Invoice Paid Notification
				'invoiceRefunded'			=>	false,	// Invoice Refunded Notification
				'invoiceLateFee'			=>	false,	// Invoice Late Fee Notification
				'invoiceCreationPreEmail'	=>	false,	// Executes as an invoice is being created
				'pendingOrder'				=>	false,	// Order Set to Pending Notification
				'orderPaid'					=>	false,	// Order Paid Notification
				'orderAccepted'				=>	false,	// Order Accepted Notification
				'orderCancelled'			=>	false,	// Order Cancelled Notification
				'orderCancelledRefunded'	=>	false,	// Order Cancelled & Refunded Notification
				'orderFraud'				=>	false,	// Order Marked As Fraud Notification
				'networkIssueAdd'			=>	false,	// New Network Issue Added Notification
				'networkIssueEdit'			=>	false,	// Network Issue Edited Notification
				'networkIssueClosed'		=>	false,	// Network Issue Closed Notification
				'cancellationRequest'		=>	false,	// New Cancellation Request Received Notification
				'shoppingCartCheckoutCompletePage' => false	// After checkout: use to catch free products
			)
		);
	}
}

function discord_log(string $msg, bool $debug = false){
	// Shortcut logging.
	if ($debug){
		$file = fopen(dirname(__FILE__) . "/output.log", 'a');
		fwrite($file, $msg . PHP_EOL);
		fclose($file);
	}
	logModuleCall("DISCORD_NOTIFICATION",$msg,"","","",array("password","pass"));
}

function get_order_info(int $orderid = 0){
	$desc = "Failed to query order information. Please check the logs for more information.";
	if (!is_int($orderid)){
		discord_log("get_order_info :: Order ID must be an INT value.");
	}else if ($orderid <= 0){
		discord_log("get_order_info :: Order ID must be greater than 0.");
	}else{
		// Get order information
		$r = localAPI( 'GetOrders', array('id' => $orderid) );
		//discord_log(print_r($r,true),true);
		if ($r['result'] == "success"){
			$desc = "```";
			foreach($r['orders'] as &$orders){
				//discord_log("Orders::".PHP_EOL.print_r($orders,true),true);
				foreach($orders as $key => $order){
					//discord_log("Order::".PHP_EOL.print_r($order,true),true);
					$desc .= "Client:  '".$order['name']."'\n";
					foreach($order['lineitems'] as &$products){
						//discord_log("Products::".PHP_EOL.print_r($products,true),true);
						foreach($products as $key => &$p){
							//discord_log("Product::".PHP_EOL.print_r($product,true),true);
							$desc .= "Type:    '".$p['product']."'\n";
							$desc .= "Domain:  '".$p['domain']."'\n";
							$desc .= "Cycle:   '".$p['billingcycle']."'\n";
							$desc .= "Charge:  '".$p['amount']->toFull()."'\n\n";
						}
					}
				}
			}
			$desc .= "```";
		}
	}
	return $desc;
}

function get_invoice_info(int $invoice = 0){
	$desc = "Failed to query invoice information. Please check the logs for more information.";
	if (!is_int($invoice)){
		discord_log("get_invoice_info :: ID must be an INT value.");
	}else if ($invoice <= 0){
		discord_log("get_invoice_info :: ID must be greater than 0.");
	}else{
		$reg = "/\(.*\-.*\)/";
		$r = localAPI( 'GetInvoice', array('invoiceid' => $invoice) );
		//discord_log("R=".print_r($r,true).PHP_EOL,true);
		if ($r['result'] == "success"){
			$user = get_client_info($r['userid'],false,array("fullname","currency"));
			$cur_symbol = get_currency($user['currency'],array("prefix", "suffix"));
			$desc = "Client: '".$user['fullname']."'\n\n";
			$desc .= "Invoiced Items:\n";
			foreach($r['items'] as &$items){
				//discord_log("Items : ".print_r($items,true),true);
				$desc .= "```\n";
				foreach($items as $key => &$i){
					//discord_log("Item: ".print_r($i,true),true);
					$desc .= "Description:\n";
					$term="";
					foreach(preg_split("/((\r?\n)|(\r\n?))/", $i['description']) as $line){
						preg_match($reg,$line,$m);
						if (count($m) > 0){
							$desc .= "- ".str_replace($m[0],"",$line)."\n";
							$term = "- Term: ".str_replace(array("(",")"),"",$m[0])."\n";
						}else{
							$desc .= "- $line\n";
						}
					}
					if ($term != "")
						$desc .= $term;
					$desc .= "Type:   '".$i['type']."'\n";
					$desc .= "Charge:       '".$cur_symbol['prefix'].$i['amount'].$cur_symbol['suffix']."'\n\n";
				}
				$desc .= "```\n";
			}
			$duedate = new DateTime($r['duedate']);
			$desc .= "Invoice Summary:\n";
			$desc .= "```\n";
			$desc .= "Date Due:  '".$duedate->format("d/m/Y")."'\n";
			$desc .= "Status:    '".$r['status']."'\n";
			$desc .= "Method:    '".get_payment_display_name($r['paymentmethod'])."'\n";
			$desc .= "Total:     '".$cur_symbol['prefix'].$r['total'].$cur_symbol['suffix']."'\n";
			$desc .= "```\n";
			discord_log($desc,true);
		}
	}
	return $desc;
}

function get_client_info(int $userid = 0, bool $stats = false, array $fields = null){
	$desc = "Failed to query client information. Please check the logs for more information.";
	if (!is_int($userid)){
		discord_log("get_client_info :: Order ID must be an INT value.");
	}else if ($userid <= 0){
		discord_log("get_client_info :: Order ID must be greater than 0.");
	}else{
		$r = localAPI( 'GetClientsDetails', array('clientid' => $userid, 'stats' => $stats));
		if ($r['result'] == "success"){
			if (is_null($fields))
				return $r['client'];
			$desc = array();
			foreach($fields as &$field){
				$desc[$field] = $r['client'][$field];
			}
		}
	}
	return $desc;
}

function get_payment_display_name(string $pay = ""){
	$desc = "$pay.";
	if (!is_string($pay)){
		discord_log("get_payment_display_name :: Payment method must be a string value.");
	}else if ($pay == "" || $pay == null){
		discord_log("get_payment_display_name :: Payment method must not be blank or null.");
	}else{
		$r = localAPI( 'GetPaymentMethods' );
		if ($r['result'] == "success"){
			foreach($r['paymentmethods'] as &$paymethods){
				foreach($paymethods as $key => &$p){
					if ($p['module'] == "$pay"){
						$desc = $p['displayname'];
						break;
					}
				}
			}
		}
	}
	return $desc;
}

function get_currency(int $id = 0, array $fields = null){
	$desc = "";
	if (!is_int($id)){
		discord_log("get_currency_symbol :: ID must be an INT value.");
	}else if ($id <= 0){
		discord_log("get_currency_symbol :: ID must be greater than 0.");
	}else{
		$r = localAPI( 'GetCurrencies' );
		//discord_log("ccR=".print_r($r,true),true);
		if ($r['result'] == "success"){
			foreach($r['currencies']['currency'] as $key => &$c){
				//discord_log("c=".print_r($c,true),true);
				if ($c['id'] == $id){
					if (is_null($fields))
						return $c;

					$desc = array();
					foreach($fields as &$field){
						$desc[$field] = $c[$field];
					}
				}
			}
		}
	}
	return $desc;
}

function generate_msg(array $m){
	try{
		$channels = new DiscordConfig($m['index']);
		if (is_null($channels->container))
			return false;

		foreach($channels as &$channel){
			//discord_log("channel: ".print_r($channel,true),true);
			$dataPacket = array(
				'content' => $channel['discordGroupID'],
				'username' => $channel['companyName'],
				'avatar_url' => $channel['discordWebHookAvatar'],
				'embeds' => array(
					array(
						'title' => $m['title'],
						'url' => $channel['whmcsAdminURL'].$m['url'],
						'timestamp' => date(DateTime::ISO8601),
						'description' => $m['desc'],
						'color' => $channel['discordColor'],
						'author' => array(
							'name' => $m['name']
						),
						"fields" => $m['fields'] ?? array()
					)
				)
			);
			processNotification($dataPacket,$channel['discordWebHookURL']);
		}
	}catch(Exception $e){
		discord_log("generate_msg :: '".$m['index']."' :: Error: $e");
	}
}

function processNotification($dataPacket,$url)	{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataPacket));
	$output = curl_exec($curl);
	$output = json_decode($output, true);

	if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 204) {
		logModuleCall(
			'Discord Notifications', 
			'Notification Sending Failed', 
			json_encode($dataPacket), 
			print_r($output, true)
		);
	}else{
		logModuleCall(
			'Discord Notifications', 
			'Notification Successfully Sent', 
			json_encode($dataPacket),
			print_r($output, true)
		);
	}

	curl_close($curl);
}

function simpleFix($value){
	if(strlen($value) > 150){
		$value = trim(preg_replace('/\s+/', ' ', $value));
		$valueTrim = explode( "\n", wordwrap( $value, 150));
		$value = $valueTrim[0] . '...';
	}
	// Allows special characters to be displayed on Discord.
	$value = mb_convert_encoding($value, "UTF-8", "HTML-ENTITIES"); 
	return $value;
}

add_hook("ShoppingCartCheckoutCompletePage", 1, function($vars){
	generate_msg(array(
			"index"		=> "shoppingCartCheckoutCompletePage",
			"title"		=> " - New Order '#".$vars['invoiceid']."' Found!",
			"name"		=> "Order Accepted",
			"desc"		=> get_order_info($vars['orderid']),
			"url"		=> "orders.php?action=view&id=".$vars['orderid']
		)
	);
});

add_hook("InvoiceCreationPreEmail", 1, function($vars){
	generate_msg(array(
			"index"		=> "invoiceCreationPreEmail",
			"title"		=> " - New Invoice '#".$vars['invoiceid']."' Generated!",
			"name"		=> "New Invoice",
			"desc"		=> get_invoice_info($vars['invoiceid']),
			"url"		=> "invoices.php?action=edit&id=".$vars['invoiceid']
		)
	);
});

add_hook("InvoicePaid", 1, function($vars){
	generate_msg(array(
			"index"		=> "invoicePaid",
			"title"		=> " - Invoice '#".$vars['invoiceid']."' Has Been Paid!",
			"name"		=> "Invoice Paid!",
			"desc"		=> get_invoice_info($vars['invoiceid']),
			"url"		=> "invoices.php?action=edit&id=".$vars['invoiceid']
		)
	);
});

add_hook('InvoiceRefunded', 1, function($vars){
	generate_msg(array(
			"index"		=> "invoiceRefunded",
			"title"		=> " - Invoice '#".$vars['invoiceid']."' Has Been Refunded.",
			"name"		=> "Invoice Refunded.",
			"desc"		=> get_invoice_info($vars['invoiceid']),
			"url"		=> "invoices.php?action=edit&id=".$vars['invoiceid']
		)
	);
});

add_hook('AddInvoiceLateFee', 1, function($vars) {
	generate_msg(array(
			"index"		=> "invoiceLateFee",
			"title"		=> " - Invoice '#".$vars['invoiceid']."' Has Had A Late Fee Added.",
			"name"		=> "Invoice Late Fee Added.",
			"desc"		=> get_invoice_info($vars['invoiceid']),
			"url"		=> "invoices.php?action=edit&id=".$vars['invoiceid']
		)
	);
});

add_hook('AcceptOrder', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "orderAccepted",
			"title"		=> " - Order '#".$vars['orderid']."' Has Been Accepted.",
			"name"		=> "Order Accepted.",
			"desc"		=> get_order_info($vars['orderid']),
			"url"		=> "orders.php?action=view&id=".$vars['orderid']
		)
	);
});

add_hook('CancelOrder', 1, function($vars) {
	generate_msg(array(
			"index"		=> "orderCancelled",
			"title"		=> " - Order '#".$vars['orderid']."' Has Been Cancelled.",
			"name"		=> "Order Cancelled.",
			"desc"		=> get_order_info($vars['orderid']),
			"url"		=> "orders.php?action=view&id=".$vars['orderid']
		)
	);
});

add_hook('CancelAndRefundOrder', 1, function($vars) {
	generate_msg(array(
			"index"		=> "orderCancelledRefunded",
			"title"		=> " - Order '#".$vars['orderid']."' Has Been Cancelled & Refunded.",
			"name"		=> "Order Cancelled & Refunded.",
			"desc"		=> get_order_info($vars['orderid']),
			"url"		=> "orders.php?action=view&id=".$vars['orderid']
		)
	);
});

add_hook('FraudOrder', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "orderFraud",
			"title"		=> " - Order '#".$vars['orderid']."' Has Been Marked As Fraudulent.",
			"name"		=> "Order Marked As Fraud.",
			"desc"		=> get_order_info($vars['orderid']),
			"url"		=> "orders.php?action=view&id=".$vars['orderid']
		)
	);
});

add_hook('OrderPaid', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "orderPaid",
			"title"		=> " - Order '#".$vars['orderId']."' Has Been Paid.",
			"name"		=> "Order Has been Paid.",
			"desc"		=> get_order_info($vars['orderId']),
			"url"		=> "orders.php?action=view&id=".$vars['orderId']
		)
	);
});

add_hook('PendingOrder', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "pendingOrder",
			"title"		=> " - Order '#".$vars['orderid']."' Has Been Set to Pending.",
			"name"		=> "Order Was Marked as Pending.",
			"desc"		=> get_order_info($vars['orderid']),
			"url"		=> "orders.php?action=view&id=".$vars['orderid']
		)
	);
});

add_hook('NetworkIssueAdd', 1, function($vars) {
	generate_msg(array(
			"index"		=> "networkIssueAdd",
			"title"		=> " - A New Network Issue Has Been Created",
			"name"		=> "New Network Issue.",
			"desc"		=> simpleFix($vars['description']),
			"url"		=> "networkissues.php?action=manage&id=".$vars['id'],
			"fields"	=> array(
								array(
									"name"		=> "Start Date",
									"value"		=> $vars['startdate'],
									"inline"	=> true
								),
								array(
									"name"		=> "End Date",
									"value"		=> $vars['enddate'],
									"inline"	=> true
								),
								array(
									"name"		=> "Title",
									"value"		=> simpleFix($vars['title']),
									"inline"	=> true
								),
								array(
									"name"		=> "Priority",
									"value"		=> $vars['priority'],
									"inline"	=> true
								)
							)
		)
	);
});

add_hook('NetworkIssueEdit', 1, function($vars) {
	generate_msg(array(
			"index"		=> "networkIssueEdit",
			"title"		=> " - A Network Issue Has Been Edited",
			"name"		=> "Network Issue Edited.",
			"desc"		=> simpleFix($vars['description']),
			"url"		=> "networkissues.php?action=manage&id=".$vars['id'],
			"fields"	=> array(
								array(
									"name"		=> "Start Date",
									"value"		=> $vars['startdate'],
									"inline"	=> true
								),
								array(
									"name"		=> "End Date",
									"value"		=> $vars['enddate'],
									"inline"	=> true
								),
								array(
									"name"		=> "Title",
									"value"		=> simpleFix($vars['title']),
									"inline"	=> true
								),
								array(
									"name"		=> "Priority",
									"value"		=> $vars['priority'],
									"inline"	=> true
								)
							)
		)
	);
});

add_hook('NetworkIssueClose', 1, function($vars) {
	generate_msg(array(
			"index"		=> "networkIssueClose",
			"title"		=> " - A Network Issue Has Been Closed.",
			"name"		=> "Network Issue Closed.",
			"desc"		=> "",
			"url"		=> "networkissues.php?action=manage&id=".$vars['id']
		)
	);
});

add_hook('TicketOpen', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "ticketOpened",
			"title"		=> " - '#".$vars['ticketmask']."' - ".simpleFix($vars['subject']).".",
			"name"		=> "New Support Ticket.",
			"desc"		=> simpleFix($vars['message']),
			"url"		=> "supporttickets.php?action=view&id=".$vars['ticketid'],
			"fields"	=> array(
								array(
									"name"		=> "Priority",
									"value"		=> $vars['priority'],
									"inline"	=> true
								),
								array(
									"name"		=> "Department",
									"value"		=> $vars['deptname'],
									"inline"	=> true
								),
								array(
									"name"		=> "Ticket ID",
									"value"		=> "#".$vars['ticketmask'],
									"inline"	=> true
								)
							)
		)
	);
});

add_hook('TicketUserReply', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "ticketUserReply",
			"title"		=> simpleFix(" - ".$vars['subject']."."),
			"name"		=> "New Ticket Reply.",
			"desc"		=> simpleFix($vars['message']),
			"url"		=> "supporttickets.php?action=view&id=".$vars['ticketid'],
			"fields"	=> array(
								array(
									"name"		=> "Priority",
									"value"		=> $vars['priority'],
									"inline"	=> true
								),
								array(
									"name"		=> "Department",
									"value"		=> $vars['deptname'],
									"inline"	=> true
								),
							)
		)
	);
});

add_hook('TicketFlagged', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "ticketFlagged",
			"title"		=> " - A Ticket Note Has Been Added.",
			"name"		=> "Ticket Note Added.",
			"desc"		=> "",
			"url"		=> "supporttickets.php?action=view&id=".$vars['ticketid']
		)
	);
});

add_hook('TicketAddNote', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "ticketNewNote",
			"title"		=> " - A ticket has been assigned to '".$vars['adminname']."'.",
			"name"		=> "Ticket Assignment.",
			"desc"		=> "",
			"url"		=> "supporttickets.php?action=view&id=".$vars['ticketid']
		)
	);
});

add_hook('CancellationRequest', 1, function($vars)	{
	generate_msg(array(
			"index"		=> "cancellationRequest",
			"title"		=> " - A Cancellation Request Has Been Received",
			"name"		=> "New Cancellation Request.",
			"desc"		=> simpleFix($vars['reason']),
			"url"		=> "supporttickets.php?action=view&id=".$vars['ticketid'],
			"fields"	=> array(
								array(
									"name"		=> "Cancellation Type",
									"value"		=> $vars['type'],
									"inline"	=> true
								),
							)
		)
	);
});

?>
