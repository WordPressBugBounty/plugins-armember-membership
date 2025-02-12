<?php
global $wpdb, $ARMemberLite, $arm_slugs, $arm_members_class, $arm_global_settings,  $arm_payment_gateways, $arm_subscription_plans, $arm_transaction;
$posted_data = array_map( array( $ARMemberLite, 'arm_recursive_sanitize_data'), $_POST); //phpcs:ignore
$payment_gateways             = $arm_payment_gateways->arm_get_all_payment_gateways();
$global_currency              = $arm_payment_gateways->arm_get_global_currency();
$general_settings = isset($arm_global_settings->global_settings) ? $arm_global_settings->global_settings : array();
$arm_currency_decimal = isset($general_settings['arm_currency_decimal_digit']) ? $general_settings['arm_currency_decimal_digit'] : 2;
$nowDate                      = current_time( 'mysql' );
$filter_gateway = (!empty($posted_data['gateway'])) ? sanitize_text_field($posted_data['gateway']) : '0';
$filter_ptype = (!empty($posted_data['ptype'])) ? sanitize_text_field($posted_data['ptype']) : '0';
$filter_pmode = (!empty($posted_data['pmode'])) ? sanitize_text_field($posted_data['pmode']) : '0';
$filter_pstatus = (!empty($posted_data['pstatus'])) ? sanitize_text_field($posted_data['pstatus']) : '0';
$filter_search = (!empty($posted_data['search'])) ? sanitize_text_field($posted_data['search']) : '';
$default_hide                 = array(
	'arm_transaction_id'     => 'Transaction ID',

	'arm_user_fname'         => 'First Name',
	'arm_user_lname'         => 'Last Name',
	'arm_user_id'            => 'User',
	'arm_plan_id'            => 'Membership',
	'arm_payment_gateway'    => 'Gateway',
	'arm_payment_type'       => 'Payment Type',
	'arm_payer_email'        => 'Payer Email',
	'arm_transaction_status' => 'Transaction Status',
	'arm_created_date'       => 'Payment Date',
	'arm_amount'             => 'Amount',
	'arm_cc_number'          => 'Credit Card Number',
);
if($ARMemberLite->is_arm_pro_active)
{
	$default_hide = array(
	    'arm_transaction_id' => 'Transaction ID',
	    'arm_invoice_id' => 'Invoice ID',
	    'arm_user_fname' => 'First Name',
	    'arm_user_lname' => 'Last Name',
	    'arm_user_id' => 'User',
	    'arm_user_email' => 'User Email',
	    'arm_plan_id' => 'Membership',
	    'arm_payment_gateway' => 'Gateway',
	    'arm_payment_type' => 'Payment Type',
	    'arm_payer_email' => 'Payer Email',
	    'arm_transaction_status' => 'Transaction Status',
	    'arm_created_date' => 'Payment Date',
	    'arm_amount' => 'Amount',
	    'arm_cc_number' => 'Credit Card Number',
	);
}
$user_id                      = get_current_user_id();
$transaction_show_hide_column = maybe_unserialize( get_user_meta( $user_id, 'arm_transaction_hide_show_columns', true ) );

$i           = 1;
$column_hide = '';
if ( ! empty( $transaction_show_hide_column ) ) {
	foreach ( $transaction_show_hide_column as $value ) {
		if ( $value != 1 ) {
			$column_hide = $column_hide . $i . ',';
		}
		$i++;
	}
} else {
	$column_hide = '3,4';
}

if(isset($posted_data["arm_export_phistory"]) && $posted_data["arm_export_phistory"] == 1 && $ARMemberLite->is_arm_pro_active) {
    $filter_gateway = isset($_REQUEST['arm_filter_gateway']) ? sanitize_text_field($_REQUEST['arm_filter_gateway']) : '';
    $filter_ptype = isset($_REQUEST['arm_filter_ptype']) ? sanitize_text_field($_REQUEST['arm_filter_ptype']) : '';
    $filter_pmode = isset($_REQUEST['arm_filter_pmode']) ? sanitize_text_field($_REQUEST['arm_filter_pmode']) : '';
    $filter_pstatus = isset($_REQUEST['arm_filter_pstatus']) ? sanitize_text_field($_REQUEST['arm_filter_pstatus']) : '';
    $payment_start_date = isset($_REQUEST['arm_filter_pstart_date']) ? sanitize_text_field($_REQUEST['arm_filter_pstart_date']) : '';
    $payment_end_date = isset($_REQUEST['arm_filter_pend_date']) ? sanitize_text_field($_REQUEST['arm_filter_pend_date']) : '';
    $sSearch = isset($_REQUEST['armmanagesearch_new']) ? sanitize_text_field($_REQUEST['armmanagesearch_new']) : '';

    $date_time_format = $arm_global_settings->arm_get_wp_date_time_format();

    $where_plog = $wpdb->prepare("WHERE 1=1 AND arm_display_log = %d ",1);

    if (!empty($filter_gateway) && $filter_gateway != '0') {
        $where_plog .= $wpdb->prepare(" AND `arm_payment_gateway` = %s",$filter_gateway);
    }
    if (!empty($filter_ptype) && $filter_ptype != '0') {
        $where_plog .= $wpdb->prepare(" AND `arm_payment_type` = %s",$filter_ptype);
    }
    if (!empty($filter_pmode) && $filter_pmode != '0') {
        $where_plog .= $wpdb->prepare(" AND `arm_payment_mode` = %s",$filter_pmode);
    }
    
    if (!empty($filter_pstatus) && $filter_pstatus != '0') {
        $filter_pstatus = strtolower($filter_pstatus);
        $status_query = $wpdb->prepare(" AND ( LOWER(`arm_transaction_status`)=%s",$filter_pstatus);
        if(!in_array($filter_pstatus,array('success','pending','canceled'))) {
            $status_query .= ")";
        }
        switch ($filter_pstatus) {
            case 'success':
                $status_query .= $wpdb->prepare(" OR `arm_transaction_status`=%d)",1);
                break;
            case 'pending':
                $status_query .= $wpdb->prepare(" OR `arm_transaction_status`=%d)",0);
                break;
            case 'canceled':
                $status_query .= $wpdb->prepare(" OR `arm_transaction_status`=%d)",2);
                break;
        }
        $where_plog .= $status_query;
    }

    $pt_where = $bt_where = "";
    if(!empty($payment_start_date)) {
        $payment_start_date = date("Y-m-d", strtotime($payment_start_date));
        $pt_where .= $wpdb->prepare(" WHERE `pt`.`arm_created_date` >= %s",$payment_start_date);
        $bt_where .= $wpdb->prepare(" WHERE `bt`.`arm_created_date` >= %s",$payment_start_date);
    }

    if(!empty($payment_end_date)) {
        $payment_end_date = date("Y-m-d", strtotime("+1 day", strtotime($payment_end_date)));
        if($pt_where != "") $pt_where .= " AND "; else $pt_where = " WHERE ";
        $pt_where .= $wpdb->prepare(" `pt`.`arm_created_date` < %s",$payment_end_date);

        if($bt_where != "") $bt_where .= " AND "; else $bt_where = " WHERE ";
        $bt_where .= $wpdb->prepare(" `bt`.`arm_created_date` < %s",$payment_end_date);
    }

    $search_ = "";
    if ($sSearch != '') {
        $search_ = $wpdb->prepare(" AND (`arm_payment_history_log`.`arm_transaction_id` LIKE %s OR `arm_payment_history_log`.`arm_token` LIKE %s OR `arm_payment_history_log`.`arm_payer_email` LIKE %s OR `arm_payment_history_log`.`arm_created_date` LIKE %s OR `arm_payment_history_log`.`arm_first_name` LIKE %s OR `arm_payment_history_log`.`arm_last_name` LIKE %s OR `arm_user_login` LIKE %s OR `arm_user_email` LIKE %s ) ",'%'.$sSearch.'%','%'.$sSearch.'%','%'.$sSearch.'%','%'.$sSearch.'%','%'.$sSearch.'%','%'.$sSearch.'%','%'.$sSearch.'%','%'.$sSearch.'%');
    }

    if(empty($pt_where))
    {
        $pt_where .= 'WHERE 1=1';
    }

    $orderby = "ORDER BY `arm_payment_history_log`.`arm_invoice_id` DESC";
    $ctquery = "SELECT pt.arm_log_id,pt.arm_invoice_id,pt.arm_user_id,pt.arm_first_name,pt.arm_last_name,pt.arm_plan_id,pt.arm_payer_email,pt.arm_transaction_id,pt.arm_amount,pt.arm_currency,pt.arm_is_trial,pt.arm_payment_gateway,pt.arm_payment_mode,pt.arm_transaction_status,pt.arm_created_date,pt.arm_payment_type,pt.arm_extra_vars,sp.arm_subscription_plan_name,wpu.user_login as arm_user_login, wpu.user_email as arm_user_email,pt.arm_display_log as arm_display_log FROM `" . $ARMember->tbl_arm_payment_log . "` pt LEFT JOIN `" . $ARMember->tbl_arm_subscription_plans . "` sp ON pt.arm_plan_id = sp.arm_subscription_plan_id LEFT JOIN `" . $wpdb->users . "` wpu ON pt.arm_user_id = wpu.ID " . $pt_where." AND arm_is_post_payment = 0 AND arm_paid_post_id = 0 AND arm_is_gift_payment = 0";
    $ptquery = "{$ctquery}";
        
    $payment_grid_query = "SELECT * FROM (" . $ptquery . ") AS arm_payment_history_log {$where_plog} {$search_} {$orderby}";

    $payment_log = $wpdb->get_results($payment_grid_query, ARRAY_A); //phpcs:ignore --Reason $payment_grid_query is a query

        $final_log = array();
        $tmp = array (
            "Transaction_Id" => '',
            "Invoice_Id" => '',
            "First_Name" => '',
            "Last_Name" => '',
            "User" => '',
            "User Email" => '',
            "Membership" => '',
            "Gateway" => '',
            "Payment_Type" => '',
            "Payer_Email" => '',
            "Transaction_Status" => '',
            "Payment_Date" => '',
            "Amount" => '',
            "Credit_Card_Number" => ''
        );
        $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
        $arm_all_plan_arr = array();
        foreach ($payment_log as $row) {
            $ccn = maybe_unserialize($row["arm_extra_vars"]);
            $arm_transaction_status = $row["arm_transaction_status"];
            switch ($arm_transaction_status) {
                case '0':
                    $arm_transaction_status = 'pending';
                    break;
                case '1':
                    $arm_transaction_status = 'success';
                    break;
                case '2':
                    $arm_transaction_status = 'canceled';
                    break;
                default:
                    $arm_transaction_status = $row["arm_transaction_status"];
                    break;
            }
            $tmp["Transaction_Id"] = $row["arm_transaction_id"];
            if($tmp["Transaction_Id"] == "-") {
                $tmp["Transaction_Id"] = "";
            }
            $tmp["Invoice_Id"] = $row["arm_invoice_id"];
            $tmp["First_Name"] = $row["arm_first_name"];
            $tmp["Last_Name"] = $row["arm_last_name"];
            $tmp["User"] = $row["arm_user_login"];
            $tmp["User Email"] = $row["arm_user_email"];
            $tmp["Membership"] = $row["arm_subscription_plan_name"];
            $tmp["Gateway"] = $row["arm_payment_gateway"] == "" ? esc_html__('Manual', 'armember-membership') : $arm_payment_gateways->arm_gateway_name_by_key($row["arm_payment_gateway"]);
            $tmp["Payment_Type"] = "";
            $tmp["Payer_Email"] = $row["arm_payer_email"];
            $tmp["Transaction_Status"] = $arm_transaction_status;
            $tmp["Payment_Date"] = date_i18n($date_time_format, strtotime($row["arm_created_date"]));
            $tmp["Amount"] = number_format($row["arm_amount"],$arm_currency_decimal) . " " . $row["arm_currency"];
            $tmp["Credit_Card_Number"] = isset($ccn["card_number"]) ? $ccn["card_number"] : '';
            if($tmp["Credit_Card_Number"] == "-") {
                $tmp["Credit_Card_Number"] = "";
            }

            $log_payment_mode = $row["arm_payment_mode"];
            $plan_id = $row["arm_plan_id"];
            $user_id = $row["arm_user_id"];
            $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
            $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
            $oldPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);
            $arm_old_plan_detail = $oldPlanData['arm_current_plan_detail'];
            if (!empty($arm_old_plan_detail)) {
                $plan_info = new ARM_Plan($plan_id);
                $plan_info->init((object) $arm_old_plan_detail);
            }
            else
            {
                if(!empty($arm_all_plan_arr[$plan_id]))
                {
                    $plan_info = $arm_all_plan_arr[$plan_id];
                }
                else
                {
                    $plan_info = new ARM_Plan($plan_id);
                    $arm_all_plan_arr[$plan_id] = $plan_info;
                }
            }
            //$plan_info = new ARM_Plan($plan_id);
            $payment_type_text = $user_payment_mode = "";

            $payment_type = $row['arm_payment_type'];

            if($plan_info->is_recurring()) {
                if($log_payment_mode != '') {
                    if($log_payment_mode == 'manual_subscription') {
                        $user_payment_mode .= "";
                    }
                    else {
                        $user_payment_mode .= "(" . esc_html__('Automatic','armember-membership') . ")";
                    }
                }
                //$payment_type = 'subscription';
                $payment_type = $plan_info->options['payment_type'];
            }

            if($payment_type =='one_time') {
                $payment_type_text = esc_html__('One Time', 'armember-membership');
            }
            else if($payment_type == 'subscription') {
                $payment_type_text = esc_html__('Subscription', 'armember-membership');
            }

            if($row["arm_is_trial"] == 1) {
                $arm_trial = "(" . esc_html__('Trial Transaction','armember-membership') . ")";
            }
            else {
                $arm_trial = '';
            }

            $tmp["Payment_Type"] = $payment_type_text . " " . $user_payment_mode . " " . $arm_trial;
            array_push($final_log, $tmp);
        }

        ob_clean();
        ob_start();
        $now = gmdate("D, d M Y H:i:s");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Disposition: attachment;filename=ARMember-export-payment-history.csv");
        header("Content-Transfer-Encoding: binary");
        $df = fopen("php://output", 'w');
        fputcsv($df, array_keys($tmp));
        if(!empty($final_log)) {
            foreach ($final_log as $row) {
                fputcsv($df, $row);
            }
        }
        fclose($df);
        exit;
}
?>
<style type="text/css">
	#armmanagesearch_new{
		width:150px;
	}
	@media all and ( min-width:1400px ){
		#armmanagesearch_new{
			width:200px;
		}
	}
	@media all and ( min-width:1600px ){
		#armmanagesearch_new{
			width:250px;
		}
	}
</style>
<script type="text/javascript" charset="utf-8">
// <![CDATA[

	jQuery(document).ready(function () {
		arm_load_transaction_list_grid(false);
	});

	function arm_load_trasaction_list_filtered_grid() {
		jQuery('#arm_payment_grid_filter_btn').attr('disabled', 'disabled');
		jQuery('#armember_datatable').dataTable().fnDestroy();
		arm_load_transaction_list_grid(true);
	}

	function arm_load_transaction_list_grid(is_filtered) {

	var __ARM_Showing = '<?php echo addslashes( esc_html__( 'Showing', 'armember-membership' ) ); //phpcs:ignore ?>';
	var __ARM_Showing_empty = '<?php echo addslashes( esc_html__( 'Showing 0 to 0 of 0 entries', 'armember-membership' ) ); //phpcs:ignore ?>';
	var __ARM_to = '<?php echo addslashes( esc_html__( 'to', 'armember-membership' ) ); //phpcs:ignore ?>';
	var __ARM_of = '<?php echo addslashes( esc_html__( 'of', 'armember-membership' ) ); //phpcs:ignore ?>'; 
	var __ARM_transactions = '<?php esc_html_e( 'entries', 'armember-membership' ); //phpcs:ignore ?>'; 
	var __ARM_Show = '<?php echo addslashes( esc_html__( 'Show', 'armember-membership' ) ); //phpcs:ignore ?>';
	var __ARM_NO_FOUNT = '<?php echo addslashes( esc_html__( 'No any transaction found yet.', 'armember-membership' ) ); //phpcs:ignore ?>'; 
	var __ARM_NO_MATCHING = '<?php echo addslashes( esc_html__( 'No matching transactions found.', 'armember-membership' ) ); //phpcs:ignore ?>';

		var payment_gateway = jQuery("#arm_filter_gateway").val();
		var payment_type = jQuery("#arm_filter_ptype").val();
		var payment_mode = jQuery("#arm_filter_pmode").val();
		var payment_status = jQuery("#arm_filter_pstatus").val();
		var search_term = jQuery("#armmanagesearch_new").val();
		var payment_start_date = jQuery("#arm_filter_pstart_date").val();
		var payment_end_date = jQuery("#arm_filter_pend_date").val();
		var ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); //phpcs:ignore ?>";
		var filtered_data = (typeof is_filtered !== 'undefined' && is_filtered !== false) ? true : false;
		var _wpnonce = jQuery('input[name="arm_wp_nonce"]').val();


		var nColVisCols = [];
		var arm_cols_hide = '<?php echo count( $default_hide ); ?>';
		for( var cv = 1; cv <= arm_cols_hide ; cv++ ){
			nColVisCols.push( cv );
		}

		var oTables = jQuery('#armember_datatable').dataTable({
			"bProcessing": false,
			"oLanguage": {
					"sInfo": __ARM_Showing + " _START_ " + __ARM_to + " _END_ " + __ARM_of + " _TOTAL_ " + __ARM_transactions,
					"sInfoEmpty": __ARM_Showing_empty,
					"sLengthMenu": __ARM_Show + "_MENU_" + __ARM_transactions,
					"sEmptyTable": __ARM_NO_FOUNT,
					"sZeroRecords": __ARM_NO_MATCHING
				},
			"language":{
				"searchPlaceholder":"<?php esc_html_e( 'Search', 'armember-membership' ); ?>",
				"search":"",
			},
			"buttons":[{
				"extend":"colvis",
				"columns":nColVisCols,
				"className":"ColVis_Button TableTools_Button ui-button ui-state-default ColVis_MasterButton",
				"text":"<span class=\"armshowhideicon\" style=\"background-image: url(<?php echo MEMBERSHIPLITE_IMAGES_URL; //phpcs:ignore ?>/show_hide_icon.png);background-repeat: no-repeat;background-position: 8px center;padding: 0 10px 0 30px;background-color: #FFF;\"><?php esc_html_e( 'Show / Hide columns', 'armember-membership' ); ?></span>",
			}],    
			"bServerSide": true,
			"sAjaxSource": __ARMAJAXURL,
			"sServerMethod": "POST",
			"fnServerParams": function (aoData) {
				aoData.push({"name": "action", "value": "arm_load_transactions"});
				aoData.push({"name": "gateway", "value": payment_gateway});
				aoData.push({"name": "payment_type", "value": payment_type});
				aoData.push({"name": "payment_status", "value": payment_status});
				aoData.push({"name": "payment_mode", "value": payment_mode});
				aoData.push({"name": "payment_start_date", "value": payment_start_date});
				aoData.push({"name": "payment_end_date", "value": payment_end_date});
				aoData.push({"name": "sSearch", "value": search_term});
				aoData.push({"name": "sColumns", "value": null});
				aoData.push({"name": "_wpnonce", "value": _wpnonce});
			},
			"bRetrieve": false,
			"sDom": '<"H"CBfr>t<"footer"ipl>',
			"sPaginationType": "four_button",
			"bJQueryUI": true,
			"bPaginate": true,
			"bAutoWidth": false,
			"sScrollX": "100%",
			"bScrollCollapse": true,
			"oColVis": {
				"aiExclude": [0, <?php echo count( $default_hide ) + 1; ?>]
			},
			"aoColumnDefs": [
				{"aTargets":[0],"sClass":"noVis"},
				{"sType": "html", "bVisible": false, "aTargets": [<?php echo $column_hide; //phpcs:ignore ?>]},
				{"bSortable": false, "aTargets": [0]}
			],
			"bStateSave": true,
			"iCookieDuration": 60 * 60,
			"sCookiePrefix": "arm_datatable_",
			"aLengthMenu": [10, 25, 50, 100, 150, 200],
			"fnPreDrawCallback": function () {
				jQuery('#transactions_list_form .arm_loading_grid').show();
			},
			"fnCreatedRow": function( nRow, aData, iDataIndex ) {
				jQuery(nRow).find('.arm_grid_action_btn_container').each(function () {
					jQuery(this).parent().addClass('armGridActionTD');
					jQuery(this).parent().attr('data-key', 'armGridActionTD');
				});
			},
			"fnDrawCallback": function () {
				arm_show_data();
				jQuery('#transactions_list_form .arm_loading_grid').hide();
				jQuery(".cb-select-all-th").removeClass('sorting_asc');
				jQuery("#cb-select-all-1").prop("checked", false);
				arm_selectbox_init();
				if (filtered_data == true) {
					var filter_box = jQuery('#arm_filter_wrapper_after_filter').html();
					jQuery('div#armember_datatable_filter').parent().append(filter_box);
					jQuery('div#armember_datatable_filter').hide();
				}
				filtered_data = false;
				if (jQuery.isFunction(jQuery().tipso)) {
					jQuery('.armhelptip').each(function () {
						jQuery(this).tipso({
							position: 'top',
							size: 'small',
							background: '#939393',
							color: '#ffffff',
							width: false,
							maxWidth: 400,
							useTitle: true
						});
					});
				}
				jQuery('#arm_payment_grid_filter_btn').removeAttr('disabled');
			},
			"fnStateSave": function (oSettings, oData) {
				oData.aaSorting = [];
				oData.abVisCols = [];
				oData.aoSearchCols = [];
				oData.iStart = 0;
				this.oApi._fnCreateCookie(
					oSettings.sCookiePrefix + oSettings.sInstance,
					this.oApi._fnJsonString(oData),
					oSettings.iCookieDuration,
					oSettings.sCookiePrefix,
					oSettings.fnCookieCallback
					);
			},
			"stateSaveParams":function(oSettings,oData){
				oData.start=0;
			},
			"fnStateLoadParams": function (oSettings, oData) {
				oData.iLength = 10;
				oData.iStart = 1;
			   // oData.oSearch.sSearch = search_term;
			},
		});
		var filter_box = jQuery('#arm_filter_wrapper').html();
		jQuery('div#armember_datatable_filter').parent().append(filter_box);
		jQuery('div#armember_datatable_filter').hide();
		jQuery('#arm_filter_wrapper').remove();
		jQuery('#armmanagesearch_new').on('keyup', function (e) {
			e.stopPropagation();
			if (e.keyCode == 13) {
				var gateway = jQuery('#arm_filter_gateway').val();
				var ptype = jQuery('#arm_filter_ptype').val();
				var pstatus = jQuery('#arm_filter_pstatus').val();
				var search = jQuery('#armmanagesearch_new').val();
				arm_reload_log_list(gateway, ptype, pstatus, search);
				return false;
			}
		});
	}
	function ChangeID(id, type)
	{
		document.getElementById('delete_id').value = id;
		document.getElementById('delete_type').value = type;
	}
	function ChangeStatus(id, status)
	{
		document.getElementById('log_id').value = id;
		document.getElementById('log_status').value = status;
	}
// ]]>
</script>
<div class="arm_filter_wrapper" id="arm_filter_wrapper_after_filter" style="display:none;">

	<div class="arm_datatable_filters_options">
		<div class='sltstandard'>
			<input type='hidden' id='arm_transaction_bulk_action1' name="action1" value="-1" />
			<dl class="arm_selectbox column_level_dd arm_width_160">
				<dt><span></span><input type="text" style="display:none;" value="" class="arm_autocomplete"  /><i class="armfa armfa-caret-down armfa-lg"></i></dt>
				<dd>
					<ul data-id="arm_transaction_bulk_action1">
						<li data-label="<?php esc_attr_e( 'Bulk Actions', 'armember-membership' ); ?>" data-value="-1"><?php esc_html_e( 'Bulk Actions', 'armember-membership' ); ?></li>
						<li data-label="<?php esc_attr_e( 'Delete', 'armember-membership' ); ?>" data-value="delete_transaction"><?php esc_html_e( 'Delete', 'armember-membership' ); ?></li>
					</ul>
				</dd>
			</dl>
		</div>
		<input type="submit" id="doaction1" class="armbulkbtn armemailaddbtn" value="<?php esc_attr_e( 'Go', 'armember-membership' ); ?>"/>
	</div>

</div>
<div class="arm_transactions_list">
	<div class="arm_filter_wrapper" id="arm_filter_wrapper" style="display:none;">
		<div class="arm_datatable_filters_options">
			<div class='sltstandard'>
				<input type='hidden' id='arm_transaction_bulk_action1' name="action1" value="-1" />
				<dl class="arm_selectbox column_level_dd arm_width_160">
					<dt><span></span><input type="text" style="display:none;" value="" class="arm_autocomplete" /><i class="armfa armfa-caret-down armfa-lg"></i></dt>
					<dd>
						<ul data-id="arm_transaction_bulk_action1">
							<li data-label="<?php esc_attr_e( 'Bulk Actions', 'armember-membership' ); ?>" data-value="-1"><?php esc_html_e( 'Bulk Actions', 'armember-membership' ); ?></li>
							<li data-label="<?php esc_attr_e( 'Delete', 'armember-membership' ); ?>" data-value="delete_transaction"><?php esc_html_e( 'Delete', 'armember-membership' ); ?></li>
						</ul>
					</dd>
				</dl>
			</div>
			<input type="submit" id="doaction1" class="armbulkbtn armemailaddbtn" value="<?php esc_attr_e( 'Go', 'armember-membership' ); ?>"/>
		</div>
	</div>
	<form method="GET" id="transactions_list_form" class="data_grid_list" onsubmit="return arm_transactions_list_form_bulk_action();">
		<input type="hidden" name="page" value="<?php echo esc_attr($arm_slugs->transactions); //phpcs:ignore ?>" />
		<input type="hidden" name="armaction" value="list" />
		<div class="arm_datatable_filters">
			<div class="arm_dt_filter_block arm_datatable_searchbox">
				<label><input type="text" placeholder="<?php esc_attr_e( 'Search', 'armember-membership' ); ?>" id="armmanagesearch_new" value="<?php echo esc_attr($filter_search); ?>" tabindex="-1" ></label>
				<?php if ( ! empty( $payment_gateways ) ) : ?>
					<!--./====================Begin Filter By Payment Gateway Box====================/.-->
					<div class="arm_datatable_filter_item arm_filter_gateway_label">
						<input type="hidden" id="arm_filter_gateway" class="arm_filter_gateway" value="<?php echo esc_attr($filter_gateway); ?>" />
						<dl class="arm_selectbox arm_width_160">
							<dt><span></span><input type="text" style="display:none;" value="" class="arm_autocomplete"/><i class="armfa armfa-caret-down armfa-lg"></i></dt>
							<dd>
								<ul data-id="arm_filter_gateway">
									<li data-label="<?php esc_attr_e( 'Gateway', 'armember-membership' ); ?>" data-value="0"><?php esc_html_e( 'Gateway', 'armember-membership' ); ?></li>
									<li data-label="<?php esc_attr_e( 'Manual', 'armember-membership' ); ?>" data-value="<?php esc_attr_e( 'manual', 'armember-membership' ); ?>"><?php esc_html_e( 'Manual', 'armember-membership' ); ?></li>
									<?php foreach ( $payment_gateways as $key => $pg ) : ?>
										<li data-label="<?php echo esc_attr($pg['gateway_name']); ?>" data-value="<?php echo esc_attr($key); ?>"><?php echo esc_attr($pg['gateway_name']); ?></li>                                    
									<?php endforeach; ?>
								</ul>
							</dd>
						</dl>
					</div>
					<!--./====================End Filter By Payment Gateway Box====================/.-->
				<?php endif; ?>
				<!--./====================Begin Filter By Payment Type Box====================/.-->
				<div class="arm_datatable_filter_item arm_filter_ptype_label">
					<input type="hidden" id="arm_filter_ptype" class="arm_filter_ptype" value="<?php echo esc_html($filter_ptype); ?>" />
					<dl class="arm_selectbox arm_width_160 arm_min_width_60">
						<dt><span></span><input type="text" style="display:none;" value="" class="arm_autocomplete"/><i class="armfa armfa-caret-down armfa-lg"></i></dt>
						<dd>
							<ul data-id="arm_filter_ptype">
								<li data-label="<?php esc_attr_e( 'Payment Type', 'armember-membership' ); ?>" data-value="0"><?php esc_html_e( 'Payment Type', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'One Time', 'armember-membership' ); ?>" data-value="one_time"><?php esc_html_e( 'One Time', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Recurring', 'armember-membership' ); ?>" data-value="subscription"><?php esc_html_e( 'Recurring', 'armember-membership' ); ?></li>
							</ul>
						</dd>
					</dl>
				</div>
				<!--./====================End Filter By Payment Type Box====================/.-->

				<!--./====================Begin Filter By Payment Mode Box====================/.-->
				<div class="arm_datatable_filter_item arm_filter_pmode_label">
					<input type="hidden" id="arm_filter_pmode" class="arm_filter_pmode" value="<?php echo esc_html($filter_pmode); ?>" />
					<dl class="arm_selectbox arm_width_160 arm_min_width_80">
						<dt><span></span><input type="text" style="display:none;" value="" class="arm_autocomplete"/><i class="armfa armfa-caret-down armfa-lg"></i></dt>
						<dd>
							<ul data-id="arm_filter_pmode">
								<li data-label="<?php esc_attr_e( 'Subscription', 'armember-membership' ); ?>" data-value="0"><?php esc_html_e( 'Subscription', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Automatic Subscription', 'armember-membership' ); ?>" data-value="auto_debit_subscription"><?php esc_html_e( 'Automatic Subscription', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Semi Automatic Subscription', 'armember-membership' ); ?>" data-value="manual_subscription"><?php esc_html_e( 'Semi Automatic Subscription', 'armember-membership' ); ?></li>
							</ul>
						</dd>
					</dl>
				</div>
				<!--./====================End Filter By Payment Mode Box====================/.-->
				<!--./====================Begin Filter By Payment Status Box====================/.-->
				<div class="arm_datatable_filter_item arm_filter_pstatus_label">
					<input type="hidden" id="arm_filter_pstatus" class="arm_filter_pstatus" value="<?php echo esc_html($filter_pstatus); ?>" />
					<dl class="arm_selectbox arm_min_width_60 arm_width_160">
						<dt><span></span><input type="text" style="display:none;" value="" class="arm_autocomplete"/><i class="armfa armfa-caret-down armfa-lg"></i></dt>
						<dd>
							<ul data-id="arm_filter_pstatus">
								<li data-label="<?php esc_attr_e( 'Status', 'armember-membership' ); ?>" data-value="0"><?php esc_html_e( 'Status', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Success', 'armember-membership' ); ?>" data-value="success"><?php esc_html_e( 'Success', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Pending', 'armember-membership' ); ?>" data-value="pending"><?php esc_html_e( 'Pending', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Cancelled', 'armember-membership' ); ?>" data-value="canceled"><?php esc_html_e( 'Cancelled', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Failed', 'armember-membership' ); ?>" data-value="failed"><?php esc_html_e( 'Failed', 'armember-membership' ); ?></li>
								<li data-label="<?php esc_attr_e( 'Expired', 'armember-membership' ); ?>" data-value="expired"><?php esc_html_e( 'Expired', 'armember-membership' ); ?></li>
							</ul>
						</dd>
					</dl>
				</div>
				<!--./====================End Filter By Payment Status Box====================/.-->
			</div>
			<div>
				<!--./====================Begin Filter By Date====================/.-->
				<div class="arm_datatable_filter_item arm_filter_pstatus_label arm_margin_left_0" >
					<input type="text" id="arm_filter_pstart_date" placeholder="<?php esc_attr_e( 'Start Date', 'armember-membership' ); ?>" data-date_format="m/d/Y"/>
				</div>
				<div class="arm_datatable_filter_item arm_filter_pstatus_label">
					<input type="text" id="arm_filter_pend_date" placeholder="<?php esc_attr_e( 'End Date', 'armember-membership' ); ?>" data-date_format="m/d/Y"/>
				</div>
				<!--./====================End Begin Filter By Date====================/.-->
			
				<div class="arm_dt_filter_block arm_dt_filter_submit arm_payment_history_filter_submit">
					<input type="button" class="armemailaddbtn" id="arm_payment_grid_filter_btn" value="<?php esc_attr_e( 'Filter', 'armember-membership' ); ?>" onClick="arm_load_trasaction_list_filtered_grid()"/>
					<?php if($ARMemberLite->is_arm_pro_active){ ?>
						<input type="button" class="armemailaddbtn arm_cancel_btn" id="arm_payment_grid_export_btn" value="<?php esc_html_e('Export To CSV', 'armember-membership'); ?>"/>
					<?php }
					?>
				</div>
			</div>
			<div class="armclear"></div>
		</div>
		<div id="armmainformnewlist" class="arm_filter_grid_list_container">
			<div class="arm_loading_grid" style="display: none;"><img src="<?php echo esc_attr(MEMBERSHIPLITE_IMAGES_URL); //phpcs:ignore ?>/loader.gif" alt="Loading.."></div>
			<div class="response_messages"></div>
			<table cellpadding="0" cellspacing="0" border="0" class="display arm_hide_datatable" id="armember_datatable">
				<thead>
					<tr>
						<th class="center cb-select-all-th arm_max_width_60" ><input id="cb-select-all-1" type="checkbox" class="chkstanard"></th>
						<th><?php esc_html_e( 'Transaction ID', 'armember-membership' ); ?></th>
						<?php if($ARMemberLite->is_arm_pro_active){ ?> <th><?php esc_html_e('Invoice ID', 'armember-membership'); ?></th> <?php } ?>
						<th><?php esc_html_e( 'First Name', 'armember-membership' ); ?></th>
						<th><?php esc_html_e( 'Last Name', 'armember-membership' ); ?></th>
						<th><?php esc_html_e( 'User', 'armember-membership' ); ?></th>
						<?php if($ARMemberLite->is_arm_pro_active){ ?> <th><?php esc_html_e('User Email', 'armember-membership'); ?></th> <?php } ?>
						<th class="arm_min_width_150"><?php esc_html_e( 'Membership', 'armember-membership' ); ?></th>
						<th><?php esc_html_e( 'Payment Gateway', 'armember-membership' ); ?></th>
						<th><?php esc_html_e( 'Payment Type', 'armember-membership' ); ?></th>
						<th><?php esc_html_e( 'Payer Email', 'armember-membership' ); ?></th>
						<th class="center"><?php esc_html_e( 'Transaction Status', 'armember-membership' ); ?></th>
						<th class="center arm_min_width_150" ><?php esc_html_e( 'Payment Date', 'armember-membership' ); ?></th>
						<th class="center"><?php esc_html_e( 'Amount', 'armember-membership' ); ?></th>
						<th class="center arm_min_width_150"><?php esc_html_e( 'Credit Card Number', 'armember-membership' ); ?></th>
						<th data-key="armGridActionTD" class="armGridActionTD" style="display: none;"></th>
					</tr>
				</thead>
			</table>
			<div class="armclear"></div>
			<input type="hidden" name="show_hide_columns" id="show_hide_columns" value="<?php esc_attr_e( 'Show / Hide columns', 'armember-membership' ); ?>"/>
			<input type="hidden" name="search_grid" id="search_grid" value="<?php esc_attr_e( 'Search', 'armember-membership' ); ?>"/>
			<input type="hidden" name="entries_grid" id="entries_grid" value="<?php esc_attr_e( 'transactions', 'armember-membership' ); ?>"/>
			<input type="hidden" name="show_grid" id="show_grid" value="<?php esc_attr_e( 'Show', 'armember-membership' ); ?>"/>
			<input type="hidden" name="showing_grid" id="showing_grid" value="<?php esc_attr_e( 'Showing', 'armember-membership' ); ?>"/>
			<input type="hidden" name="to_grid" id="to_grid" value="<?php esc_attr_e( 'to', 'armember-membership' ); ?>"/>
			<input type="hidden" name="of_grid" id="of_grid" value="<?php esc_attr_e( 'of', 'armember-membership' ); ?>"/>
			<input type="hidden" name="no_match_record_grid" id="no_match_record_grid" value="<?php esc_attr_e( 'No matching transactions found', 'armember-membership' ); ?>"/>
			<input type="hidden" name="no_record_grid" id="no_record_grid" value="<?php esc_attr_e( 'No any transaction found yet.', 'armember-membership' ); ?>"/>
			<input type="hidden" name="filter_grid" id="filter_grid" value="<?php esc_attr_e( 'filtered from', 'armember-membership' ); ?>"/>
			<input type="hidden" name="totalwd_grid" id="totalwd_grid" value="<?php esc_attr_e( 'total', 'armember-membership' ); ?>"/>
			<?php $wpnonce = wp_create_nonce( 'arm_wp_nonce' );?>
			<input type="hidden" name="arm_wp_nonce" value="<?php echo esc_attr($wpnonce);?>"/>
		</div>
		<div class="footer_grid"></div>
	</form>
</div>

<div class="arm_member_view_detail_container"></div>
