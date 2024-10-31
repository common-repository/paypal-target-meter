<?php


function update_paypal_totals() {
	global $wp_registered_widget_controls;
	$sidebars_widgets = wp_get_sidebars_widgets();
	if ( is_array($sidebars_widgets) ) {
		foreach ( $sidebars_widgets as $sidebar => $widgets ) {
			if ('wp_inactive_widgets' == $sidebar )
				continue;
			if ( is_array($widgets) ) {
				foreach ( $widgets as $widget ) {
					if(_get_widget_id_base($widget) == "paypal_target_meter_widget") {
						$wp_registered_widget_controls[$widget]['callback'][0]->get_paypal_totals();
					}
				}
			}
		}
	}
}
class PaypalTargetMeterWidget extends WP_Widget {
	function PaypalTargetMeterWidget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'paypal_target_meter_widget', 'description' => 'A Widget that displays currently collected and target amounts for a given time period.' );
		/* Widget control settings. */
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'paypal_target_meter_widget' );
		/* Create the widget. */
		$this->WP_Widget( 'paypal_target_meter_widget', 'Paypal Target Meter Widget', $widget_ops, $control_ops );
	}
	function get_paypal_totals($debug = 0) {
		$all_instances = $this->get_settings();
		$instance = $all_instances[$this->number];
		$date_range = $instance['date_range'];
		$pp_api_username = $instance['pp_api_username'];
		$pp_api_password = $instance['pp_api_password'];
		$pp_api_secret = $instance['pp_api_secret'];
		$recipient_email = $instance['recipient_email'];
		// Set up your API credentials, PayPal end point, and API version.
		$api_endpoint = "https://api-3t.paypal.com/nvp";
		if("sandbox" === $environment || "beta-sandbox" === $environment) {
			$api_endpoint = "https://api-3t.$environment.paypal.com/nvp";
		}
		$version = urlencode('51.0');

		// Set the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_endpoint);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// Set the API operation, version, and API signature in the request.
		$nvpreq = "METHOD=TransactionSearch&VERSION=$version&PWD=$pp_api_password&USER=$pp_api_username&SIGNATURE=$pp_api_secret";
		$nvpreq .= "&TransactionClass=Received";	
	
		$nvpreq .= "&RECEIVER=".urlencode($recipient_email);
	
		if( 'month' == $date_range) {
			$start_date = strtotime(date('m').'/01/'.date('Y').' 00:00:00');
			$iso_start = date('Y-m-d\T24:00:00\Z', $start_date);
		} else if ( 'year' == $date_range) {
			$start_date = strtotime('01/01/'.date('Y').' 00:00:00');
			$iso_start = date('Y-m-d\T24:00:00\Z', $start_date);
		} else {
			$start_date = strtotime('01/01/2000 00:00:00');
			$iso_start = date('Y-m-d\T24:00:00\Z', $start_date);
		}
		$nvpreq .= "&STARTDATE=$iso_start";
		if($debug){echo "Sending Request:\n" . $api_endpoint . "?" . $nvpreq . "\n\n";}
		// Set the request as a POST FIELD for curl.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		// Get response from the server.
		$httpResponse = curl_exec($ch);

		if(!$httpResponse) {
			throw new Exception("$methodName_ failed: ".curl_error($ch).'('.curl_errno($ch).')');
		}
		if($debug){echo "Raw Response:\n" . $httpResponse . "\n\n";}
		
		// Extract the response details.
		$httpResponseAr = explode("&", $httpResponse);

		$httpParsedResponseAr = array();
		$transactions  = array();
		foreach ($httpResponseAr as $i => $value) {
			$tmpAr = explode("=", $value);
			if(sizeof($tmpAr) > 1) {
				if($debug){echo "param ". $tmpAr[0] . ": ";}
				if(preg_match('/^L_(?P<key>\w+?)(?P<ai>\d+)$/',$tmpAr[0],$matches)) {
					$k = $matches['key'];
					$ai = intval($matches['ai']);
					if($debug){echo "parsing transaction $k ( $ai )\n";}
					if(!is_array($transactions[$ai])) { $transactions[$ai] = array();}
					$transactions[$ai][$k] = urldecode($tmpAr[1]);
				} else {
					if($debug){echo "can't decode!\n";}
					$httpParsedResponseAr[$tmpAr[0]] = urldecode($tmpAr[1]);
				}
			}
		}

		if((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
			throw new Exception("Invalid HTTP Response for POST request to $api_ndpoint.");
		}
		if(!("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"]))) {
			throw new Exception('TransactionSearch failed: ' . print_r($httpParsedResponseAr, true));
		}

		$ret = array();
		$cleared = 0.00;
		$pending = 0.00;
		if($debug){echo "Transactions:\n";}
		if($debug){echo "TRANSACTIONID|TIMESTAMP|STATUS|AMT\n";}
			
		foreach ($transactions as $t) {
			if($debug){echo $t['TRANSACTIONID'] . "|" . $t['TIMESTAMP'] . "|" . $t['STATUS'] . "|" . $t['AMT'] . "\n";}
			if($t['STATUS'] == 'Completed') { $cleared += floatval($t["AMT"]);}
			if($t['STATUS'] == 'Uncleared') { $pending += floatval($t["AMT"]);}
		}
		$instance['cleared'] = $cleared;
		$instance['pending'] = $pending;
		$instance['last_run_time'] = time();
		$all_instances[$this->number] = $instance;
		if($debug){echo "\n\nDone\n";}
		$this->save_settings($all_instances);		
		$this->updated = true;
}

	function widget( $args, $instance ) {
		extract( $args );

		/* User-selected settings. */
		$title = apply_filters('widget_title', $instance['title'] );
		$date_range = $instance['date_range'];
		$target = $instance['target'];
		$show_target = $instance['show_target'];
		$pp_api_username = $instance['pp_api_username'];
		$pp_api_password = $instance['pp_api_password'];
		$pp_api_secret = $instance['pp_api_secret'];
		$pre_text = $instance['pre_text'];
		$post_text = $instance['post_text'];
		$recipient_email = $instance['recipient_email'];
		$error = false;
		$error_msg = "";
		$cleared = $instance['cleared'];
		$pending = $instance['pending'];
		$last_run_time = $instance['last_run_time'];
		if((time() - intval($last_run_time)) > (60*5)) {
			wp_schedule_single_event(intval($last_run_time)+5,  'update_paypal_totals_action');		
		}
#		try {
			#$amts = $this->get_paypal_totals($pp_api_username,$pp_api_password,$pp_api_secret,$environment,$date_range,$recipient_email);
			#$cleared = $amts['cleared'];
			#$pending = $amts['pending'];
#		} catch (Exception $e) {
#			$error = true;
#			$error_msg = $e->getMessage();
#		}
		
		/* Before widget (defined by themes). */
		echo $before_widget;

		/* Title of widget (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;

		/* Display name from widget settings. */
		if($error) {
			if ( current_user_can('manage_options') ) {
				echo "<div class='error'>" . $error_msg . "</div>";
			} else {
				echo "<div class='error'>An error has occured - please notify the webmaster.</div>";
			}
		} else { 
			$p = ((floatval($cleared)+floatval($pending))/floatval($target))*100;
			?>
			<style>
				.paypal_target_meter_funded {
					background: #87C442;
					border-bottom-left-radius: 4px 4px;
					border-bottom-right-radius: 4px 4px;
					border-top-left-radius: 4px 4px;
					border-top-right-radius: 4px 4px;
					height: 15px;
				}
				.paypal_target_meter_wrap {
					background: #DDD;
					border-bottom-left-radius: 4px 4px;
					border-bottom-right-radius: 4px 4px;
					border-top-left-radius: 4px 4px;
					border-top-right-radius: 4px 4px;
					height: 15px;
					left: 10px;
					margin-top: 5px;
					padding: 0px;
					width: 250px;
				}
				ul.paypal_target_meter_stats {
					border: 0px;
					left: 20px;
					margin: 0px;
					outline: 0px;
					padding: 0px;
					list-style: square;
					color: #999;
					font-size: 10px;
					text-transform: uppercase;
					padding-top:0px;
				}
				ul.paypal_target_meter_stats li.first {
					padding-left: 0px;
				}
				ul.paypal_target_meter_stats li {
					border: 0px;
					line-height: 17px;
					display: inline;
					list-style-image: none;
					list-style-type: none;
					float: left;
					margin-left: 0px;
					padding-left: 8px;
					padding-right: 8px;
					white-space: nowrap;
					border-left: 1px solid white;
					border-right: 1px solid #DDD;
					vertical-align: baseline;
				}
				ul.paypal_target_meter_stats li.last {
					padding-right: 0px;
					border-right: 0;
				}
				ul.paypal_target_meter_stats li strong {
					font-size: 11px;
					line-height: 11px;
					margin: 0px;
					padding-top: 2px;
					color: #666;
					display: block;
					font-weight: bold;
				}
			</style>
			<div class="paypal_target_meter_pre_text"><?php echo $instance['pre_text']; ?></div>
			<div class="paypal_target_meter_wrap">
				<div class="paypal_target_meter_funded" style="width: <?php echo ($p > 100 ? 100 : $p); ?>%"></div>
			</div>
			<ul class="paypal_target_meter_stats">
				<li class="first">
					<strong><?php echo intval($p); ?>%</strong>
					funded
				</li>
				<li>
					<strong>$<?php echo intval($cleared); ?></strong>
					cleared
				</li>
				<li>
					<strong>$<?php echo intval($pending); ?></strong>
					pending
				</li>
				<li class="last">
					<strong>$<?php echo intval($pending+$cleared) . ' / $' . intval($target); ?></strong>
					total
				</li>
			</ul>
			<div style="clear:both"></div>
			<div class="paypal_target_meter_post_text"><?php echo $instance['post_text']; ?></div>
			<?php
		}

		/* After widget (defined by themes). */
		echo $after_widget;
	}
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['date_range'] = strip_tags( $new_instance['date_range'] );
		$instance['target'] = strip_tags( $new_instance['target'] );
		$instance['show_target'] = strip_tags( $new_instance['show_target'] );
		$instance['pp_api_username'] = $new_instance['pp_api_username'];
		$instance['pp_api_password'] = $new_instance['pp_api_password'];
		$instance['pp_api_secret'] = $new_instance['pp_api_secret'];
		$instance['recipient_email'] = $new_instance['recipient_email'];
		$instance['environment'] = $new_instance['environment'];
		$instance['pre_text'] = $new_instance['pre_text'];
		$instance['post_text'] = $new_instance['post_text'];
		$last_run_time = $instance['last_run_time'];
                wp_schedule_single_event(intval($last_run_time)+5,  'update_paypal_totals_action');
		return $instance;
	}
	function form( $instance ) {
		/* Set up some default widget settings. */
		$defaults = array( 'title' => 'Donation Targets', 'show_target' => true, 'date_range' => 'none', 'target' => '100' );
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'date_range' ); ?>">Date Range:</label>
			<select id="<?php echo $this->get_field_id( 'date_range' ); ?>" name="<?php echo $this->get_field_name( 'date_range' ); ?>" class="widefat" style="width:100%;">
				<option value="none" <?php if ( 'none' == $instance['date_range'] ) echo 'selected="selected"'; ?>>None (get all)</option>
				<option value="month" <?php if ( 'month' == $instance['date_range'] ) echo 'selected="selected"'; ?>>This Month</option>
				<option value="year" <?php if ( 'year' == $instance['date_range'] ) echo 'selected="selected"'; ?>>This Year</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'target' ); ?>">Target Amount:</label>
			$<input id="<?php echo $this->get_field_id( 'target' ); ?>" name="<?php echo $this->get_field_name( 'target' ); ?>" value="<?php echo $instance['target']; ?>" style="width:100%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'pp_api_username' ); ?>">Paypal API Username:</label>
			<input id="<?php echo $this->get_field_id( 'pp_api_username' ); ?>" name="<?php echo $this->get_field_name( 'pp_api_username' ); ?>" value="<?php echo $instance['pp_api_username']; ?>" style="width:100%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'pp_api_password' ); ?>">Paypal API Password:</label>
			<input type="password" id="<?php echo $this->get_field_id( 'pp_api_password' ); ?>" name="<?php echo $this->get_field_name( 'pp_api_password' ); ?>" value="<?php echo $instance['pp_api_password']; ?>" style="width:100%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'pp_api_secret' ); ?>">Paypal API Secret:</label>
			<input type="password" id="<?php echo $this->get_field_id( 'pp_api_secret' ); ?>" name="<?php echo $this->get_field_name( 'pp_api_secret' ); ?>" value="<?php echo $instance['pp_api_secret']; ?>" style="width:100%;" />
		</p>
				<p>
			<label for="<?php echo $this->get_field_id( 'environment' ); ?>">Environment:</label>
			<select id="<?php echo $this->get_field_id( 'environment' ); ?>" name="<?php echo $this->get_field_name( 'environment' ); ?>" class="widefat" style="width:100%;">
				<option value="sandbox" <?php if ( 'sandbox' == $instance['environment'] ) echo 'selected="selected"'; ?>>Sandbox</option>
				<option value="beta-sandbox" <?php if ( 'beta-sandbox' == $instance['environment'] ) echo 'selected="selected"'; ?>>Beta Sandbox</option>
				<option value="live" <?php if ( 'live' == $instance['environment'] ) echo 'selected="selected"'; ?>>Live</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'recipient_email' ); ?>">Recipient Email (optional):</label>
			<input id="<?php echo $this->get_field_id( 'recipient_email' ); ?>" name="<?php echo $this->get_field_name( 'recipient_email' ); ?>" value="<?php echo $instance['recipient_email']; ?>" style="width:100%;" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('pre_text'); ?>">Code to display before Widget:</label>
			<textarea class="widefat" rows="6" cols="20" id="<?php echo $this->get_field_id('pre_text'); ?>" name="<?php echo $this->get_field_name('pre_text'); ?>"><?php echo $instance['pre_text']; ?></textarea>
		</p>
		<p>
                        <label for="<?php echo $this->get_field_id('post_text'); ?>">Code to display after Widget:</label>
                        <textarea class="widefat" rows="6" cols="20" id="<?php echo $this->get_field_id('post_text'); ?>" name="<?php echo $this->get_field_name('post_text'); ?>"><?php echo $instance['post_text']; ?></textarea>
                </p>

		<?php
	}
}	

function widget_PaypalTargetMeterWidget_init() {register_widget('PaypalTargetMeterWidget');}
add_action('update_paypal_totals_action','update_paypal_totals');

?>
