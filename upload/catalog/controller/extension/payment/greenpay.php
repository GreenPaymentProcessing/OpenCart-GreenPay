<?php

if(!defined("GREENPAY_WEBSITE")){
	define("GREENPAY_WEBSITE", "https://cpsandbox.com/");
}
if(!defined("GREENPAY_ENDPOINT")){
	define("GREENPAY_ENDPOINT", GREENPAY_WEBSITE . "OpenCart.asmx" . "/");
}

if(!defined("GREENPAY_VERSION")){
	define("GREENPAY_VERSION", '2.0.0');
}

class ControllerExtensionPaymentGreenPay extends Controller
{
    // Constants
    const SUPPORTED_LANGS = array(
        'en' => 'en' // English
    );
    const DEFAULT_LANG = 'en';

    private $money_in_trans_details;

    /**
     * @var string Holds the manual fields for just straight routing and account number
     */
    private $manualFieldsOutput = <<<'EOD'
    <div>
<div class="form-group required">
    <label for="input-shipping-firstname" class="col-sm-2 control-label">Routing Number</label>
    <div class="col-sm-10">
        <input type="text" class="form-control routing_number req" name="greenpay_routing_number"
            value="" />
        <div class="text-danger req_routing" style='display:none'>{{ error_field_empty }}</div>
    </div>
</div>
<div class="form-group required">
    <label for="input-shipping-firstname" class="col-sm-2 control-label">Account Number</label>
    <div class="col-sm-10">
        <input type="text" class="form-control account_number req" name="greenpay_account_number"
            value="" />
        <div class="text-danger req_acc" style='display:none'>{{ error_field_empty }}</div>
    </div>
</div>
</div>
EOD;

    /**
     * Versions prior to 3.0 didn't need a prefix, those after do. This handles returning the prefix everywhere in the class
     * 
     * @return string
     */
    private function prefix()
    {
        return (version_compare(VERSION, '3.0', '>=')) ? 'payment_' :  '';
    }

    /**
     * Test whether the current request was called via GET
     * 
     * @return bool
     */
    private function isGet()
    {
        return (strtoupper($this->request->server['REQUEST_METHOD']) == 'GET');
    }

    /**
     * Returns a given GET parameter 
     * 
     * @param string $key   The key we are looking for in the POST parameters.
     * 
     * @return string 
     */
    private function getValue($key)
    {
        return (isset($this->request->get[$key]) ? $this->request->get[$key] : null);
    }

    /**
     * Test whether the current request was called via POST
     * 
     * @return bool
     */
    private function isPost()
    {
        return (strtoupper($this->request->server['REQUEST_METHOD']) == 'POST');
    }

    /**
     * Returns a given POST parameter 
     * 
     * @param string $key   The key we are looking for in the POST parameters.
     * 
     * @return string 
     */
    private function postValue($key)
    {
        return (isset($this->request->post[$key]) ? $this->request->post[$key] : null);
    }

    /**
     * Get the configuration object
     * 
     * @return array
     */
    private function getGreenmoneyConfig()
    {
        $greenConfig = array();
        $greenConfig['greenpay_client_id'] = $this->config->get($this->prefix() . 'greenpay_client_id');
        $greenConfig['greenpay_store_id'] = $this->config->get($this->prefix() . 'greenpay_store_id');
        $greenConfig['greenpay_api_password'] = $this->config->get($this->prefix() . 'greenpay_api_password');
        $greenConfig['greenpay_oc_username'] = $this->config->get($this->prefix() . 'greenpay_oc_username');
        $greenConfig['greenpay_oc_key'] = $this->config->get($this->prefix() . 'greenpay_oc_key');
        $greenConfig['greenpay_domain'] = $this->config->get($this->prefix() . 'greenpay_domain');
        $greenConfig['greenpay_payment_mode'] = $this->config->get($this->prefix() . 'greenpay_payment_mode');
        $greenConfig['greenpay_verde_allowed'] = $this->config->get($this->prefix() . 'greenpay_verde_allowed');
        $greenConfig['greenpay_verde_enabled'] = $this->config->get($this->prefix() . 'greenpay_verde_enabled');
        $greenConfig['greenpay_debug'] = $this->config->get($this->prefix() . 'greenpay_debug');

        if (strlen($this->config->get($this->prefix() . 'greenpay_domain')) == 0) {
            $site_url = $this->config->get("site_ssl");
            $site_url_parts = explode("/", $site_url);
            //Site comes with /admin/ attached at the end and we only want the base OpenCart domain so we have to pop twice
            array_pop($site_url_parts);
            array_pop($site_url_parts);
            $site_url = implode("/", $site_url_parts);
            $greenConfig['greenpay_domain'] = $site_url;
        }

        return $greenConfig;
    }

    /**
     * This returns the view for selecting the payment option during checkout. I believe this returns /catalog/view/theme/default/template/extension/payment/greenpay.twig
     * 
     * @return object The View object loaded from the twig after setting data for the various links
     */
    public function index()
    {
        // Load language
        $this->load->language('extension/payment/greenpay');

        // Load Model
        $this->load->model('extension/payment/greenpay');

        $data['text_card'] = $this->language->get('text_card');
        $data['link_checkout'] = $this->url->link('extension/payment/greenpay/checkout', '', true);
        $data['button_continue'] = $this->language->get('button_continue');
        $data['text_loading'] = $this->language->get('text_loading');

        $config = $this->getGreenmoneyConfig();
        if (isset($config["greenpay_verde_allowed"]) && $config["greenpay_verde_allowed"] == true && isset($config["greenpay_verde_enabled"]) && $config["greenpay_verde_enabled"] == true) {
            $value = (float) $this->cart->getTotal();
            $widgetOptions = array(
                "Amount" => $value,
                "Display" => "modal",
                "CustomerId" => null,
                "CustomerData" => null
            );
            if (isset($_COOKIE["GreenWidgetData"])) {
                $storedData = json_decode(stripcslashes($_COOKIE["GreenWidgetData"]), true);
                if ($storedData !== null && $storedData["Customer"]) {
                    $widgetOptions["CustomerId"] = $storedData["Customer"];
                }
            }
            $widgetInjectCode = $this->call_for_widget($widgetOptions);

            if ($widgetInjectCode !== false) {
                //We got valid code
                $outputString = <<<EOT
<div id="gm_cfw_loader">
	<div style="display:flex;flex-direction:column;align-items:stretch;justify-content:space-around">
		<div class="bill-pay-type">
			<table id="gm_payment_type_select" class="" border="0">
				<tbody>
					<tr>
						<td>
							<input type="hidden" id="gm_cwf_c" name="greenpay_cfw_c" value="" />
							<input type="hidden" id="gm_cwf_a" name="greenpay_cfw_a" value="" />
							<input id="gm_payment_type_select_0" type="radio" name="greenpay_paymenttype" value="1" checked="checked" />
							<label for="gm_payment_type_select_0">
								<div class="icon-container bankLogin">
									<div class="billpay-mode">
										<div style="font-size:calc(20px + .5vw);">Bank Login</div>
									</div>
								</div>
							</label>
						</td>
					</tr>
					<tr>
						<td>
							<input id="gm_payment_type_select_1" type="radio" name="greenpay_paymenttype" value="2" />
							<label for="gm_payment_type_select_1">
								<div class="icon-container manualBank">
									<div class="billpay-mode">
										<div style="font-size:calc(20px + .5vw);">Manually Enter Account</div>
									</div>
								</div>
							</label>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
    <div id="gm_bank_login_area" style="text-align:center;">
        %s
        <div id='gm_widget_injection_holder'></div>
		<button type="button" class="btn btn-primary" id="gm_Bank_Widget_Launch" style="display:none;">Lanch Bank Login</button>
        <ul id="gm_payment_accounts" style="display:none;margin-top:1em;"></ul>
        <div class="alert alert-danger hidden" id='loginError'>You must login to your bank account before continuing!</div>
		<div>
			<script type="text/javascript">
				$ = jQuery.noConflict();
			</script>
		</div>
	</div>
	<div id="gm_manual_area" style="display:none;">
		%s
	</div>
</div>
<script type="text/javascript">
	jQuery(document).ready(function ($) {
        $("head").append("<link href='catalog/view/extension/css/greenpay.css' rel='stylesheet' />");

		var loader;
		var loaderInterval;
		GreenApplication.init();
		loadSavedBankInfo();
		var gm_data = GreenApplication.Greenflow.loadWidgetData($value, null, null, null, null);
		loader = showBSLoader($("#gm_bank_login_area"));
		loaderInterval = window.setInterval(function () {
			var ps = ($(loader).text().match(/\./g) || []).length
			if (ps === 3) {
				$(loader).text("Loading");
			} else {
				$(loader).text($(loader).text() + ".");
			}
		}, 500);
		console.log("[Green] greenpay_verde.twig ready function called. gm_data: ", gm_data);

		$("input[name='greenpay_paymenttype']").click(function () {
			var v = $(this).val();
			if (1 == v) {
				enableBankLogin();
			} else {
				enableManualLogin();
			}
		});

		if (typeof Cashflow !== "undefined") {
			Cashflow.ready = function (s) {
				console.log("[Green] Cashflow.ready called...");
				$.post("index.php?route=ajax/greenpay/startSession", {
				    "s": s,
					"c": {$config["greenpay_client_id"]}
				}).then(function (s) {
					console.log("[Green] Cashflow.ready.then called...", s);
					loader.remove();
					$("#gm_Bank_Widget_Launch").show();
					$("#gm_Bank_Widget_Launch").click(Cashflow.open);
					}, function () {
						gm_WidgetError(loader, loaderInterval);
					});
			};

			Cashflow.onFailedStartup = function (m) {
				console.log("[Green] - widget failed startup...", m);
				gm_WidgetError(loader, loaderInterval);
			};

			Cashflow.onCustomerCreation = function (x) {
				console.log("[Green] Cashflow.onCustomerCreation called...", x);
				$("#gm_cwf_c").val(x);
				var data = jQuery.parseJSON(GreenApplication.localStorage.get(GreenApplication.localKey));
				data.Customer = x;
				data.Accounts = [];
				GreenApplication.localStorage.set(GreenApplication.localKey, JSON.stringify(data));
				Cookies.set(GreenApplication.localKey, data, { expires: 1825, secure: true });
			};

			Cashflow.onEnrollmentSuccess = function (r, d) {
				console.log("[Green] Cashflow.onEnrollmentSuccess called...", r, d);
				a = r;
				$("#gm_cwf_a").val(r);

				var data = jQuery.parseJSON(GreenApplication.localStorage.get(GreenApplication.localKey));
				var newAcc = {
					"Account": a,
					"default": true
				};
				if (d !== null) {
					var newAcc = Object.assign(newAcc, d);
				}

				for (var i = 0; i < data.Accounts.length; i++) {
					data.Accounts[i].default = false;
				}

				data.Accounts.push(newAcc);
				GreenApplication.localStorage.set(GreenApplication.localKey, JSON.stringify(data));
				Cookies.set(GreenApplication.localKey, data, { expires: 1825, secure: true });
				loadSavedBankInfo();
			};

			Cashflow.init({ target: $("#gm_widget_injection_holder") });
		}

		jQuery("#gm_payment_accounts").on("click", ".gm_payment_account", function () {
			var row = $(this);
			var accT = row.data("account");

			var selected = $(".gm_payment_account.selected");
			selected.find(".chosen").html("")
			selected.removeClass("selected");

			row.find(".chosen").html("&#10004;");
			row.addClass("selected");

			jQuery("#gm_cwf_a").val(accT);
			var data = jQuery.parseJSON(GreenApplication.localStorage.get(GreenApplication.localKey));
			for (var i = 0; i < data.Accounts.length; i++) {
				if (data.Accounts[i].Account === accT) {
					jQuery("#gm_cwf_c").val(data.Customer);
				}
			}
		});
	});

	function loadSavedBankInfo() {
		var data = jQuery.parseJSON(GreenApplication.localStorage.get(GreenApplication.localKey));
		var c = data.Customer;
		var a;

		if (data.Accounts.length > 0) {
			jQuery("#gm_payment_accounts").html("");
			for (var i = 0; i < data.Accounts.length; i++) {
				var account = data.Accounts[i];
				var li = jQuery("<li>").addClass("gm_payment_account");
				li.data("account", account.Account);
				if (account.default === true) {
					a = account;
					li.addClass("selected");
					li.append("<span class='chosen'>&#10004;</span>");
				} else {
					li.append("<span class='chosen'></span>");
				}
				li.append("<span class='account_nickname'>" + account.accountNickname + "</span>");
				li.append("<span class='account_number'>******" + account.last4Digits + "</span>");
				li.append("<span class='account_institution'>" + account.institution + "</span>");

				if (account.default === true) {
					jQuery("#gm_payment_accounts").prepend(li);
				} else {
					jQuery("#gm_payment_accounts").append(li);
				}
			}
			jQuery("#gm_payment_accounts").show();
			jQuery("#gm_Bank_Widget_Launch").text("Choose A Different Account");
		}
		
		if (isVoid(a) && data.Accounts.length > 0) { a = data.Accounts[0]; }
		if (!isVoid(c) && !isVoid(a)) {
			jQuery("#gm_cwf_c").val(c);
			jQuery("#gm_cwf_a").val(a.Account);
		}
	}

	function enableBankLogin() {
		jQuery("#gm_manual_area").hide();
		jQuery("#gm_bank_login_area").show();
	}

	function enableManualLogin() {
		jQuery("#gm_bank_login_area").hide();
		jQuery("#gm_manual_area").show();
	}

	function gm_WidgetError(loader, loaderInterval) {
		console.log("[Green] gm_WidgetError called...");
		loader.remove();
		clearInterval(loaderInterval);
		var data = jQuery.parseJSON(GreenApplication.localStorage.get(GreenApplication.localKey));

		var c = data.Customer;
		var a;
		for (var i = 0; i < data.Accounts.length; i++) {
			if (data.Accounts[i].default === true) {
				a = data.Accounts[i];
			}
		}
		if (isVoid(a) && data.Accounts.length > 0) { a = data.Accounts[0]; }

		if (!isVoid(c) && !isVoid(a)) {
			jQuery("#gm_bank_login_area").prepend("<p>The bank login widget failed to load so you cannot choose a different account, but it appears you have a saved account which is available for use!</p>");
			jQuery("#gm_Bank_Widget_Launch").hide().off();
			jQuery("#gm_cwf_c").val(c);
			jQuery("#gm_cwf_a").val(a.Account);
		} else {
			jQuery("#gm_payment_type_select_0").prop("disabled", true).attr("title", "Disabled: The bank login widget failed to load. You must enter your routing and account directly.");
			jQuery("#gm_payment_type_select_1").click();
			jQuery("#gm_cwf_c").val("");
			jQuery("#gm_cwf_a").val("");
		}
	}

</script>
EOT;
                $data["verde_script"] = sprintf($outputString, $widgetInjectCode, $this->manualFieldsOutput);
            } else {
                //Failure to find the injection code so we can ONLY output the standard output fields
                $data["verde_script"] = $this->manualFieldsOutput;
            }

            return $this->load->view('extension/payment/greenpay_verde', $data);
        } else {
            return $this->load->view('extension/payment/greenpay', $data);
        }
    }

    /**
     * Handles the actual checkout process. After validating the payment, the http response is redirected to the correct spot, usually checkout/success
     */
    public function checkout()
    {
        $this->load->language('extension/payment/greenpay');
        $this->load->model('extension/payment/greenpay');
        $this->load->model('checkout/order');

        if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
            //No products & no vouchers or the items aren't in stock and we require stock, redirect to the cart
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $greenConfig = $this->getGreenmoneyConfig();
        $debug = false;
        if (isset($greenConfig["greenpay_debug"]) && $greenConfig["greenpay_debug"]) {
            $debug = true;
        }

        if ($debug) {
            error_log("[GreenPay] Beginning checkout process.");
        }

        if(isset($this->request->post["greenpay_paymenttype"])){
            $paymentType = (int)$this->request->post["greenpay_paymenttype"];
            if($paymentType == 1) {
                $this->checkout_bank($greenConfig);
            } else {
                $this->checkout_manual($greenConfig);
            }
        } else {
            $this->checkout_manual($greenConfig);
        }
    }

    /**
     * Internal helper function called by the main checkout method that will handle calling the API for a tokenized check.
     */
    private function checkout_bank($configuration){
        $debug = false;
        if (isset($configuration["greenpay_debug"]) && $configuration["greenpay_debug"]) {
            $debug = true;
        }

        if ($debug) {
            error_log("[GreenPay] Checkout process determined to be bank login.");
            error_log("[GreenPay] Current POST: \r\n" . print_r($this->request->post, true));
        }

        $c = trim($this->request->post['greenpay_cfw_c']);
        $a = trim($this->request->post['greenpay_cfw_a']);
        if(!$c || !$a || strlen($c) <= 0 || strlen($a) <= 0) {
            $this->session->data['error'] = 'You must log into your bank account and select a payment account before continuing.';
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        // Order info
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $data = array(
            "Client_ID" => $configuration['greenpay_client_id'],
            "APIPassword" => $configuration['greenpay_api_password'],
            "Store" => $configuration["greenpay_store_id"],
            "Order_ID" => $order_id,
            "CustomerToken" => $c,
            "AccountToken" => $a
        );

        $response = $this->postGreenAPI("OpenCartGenerateCheck", $data, "FTFTokenizer.asmx");
        if($response && (string)$response->Result->Code == "0"){
            if($debug){
                error_log("[GreenPay] Checkout processed. Tokenized skeleton created with ID " . (string) $response->Unique_ID);
            }
            //Success, check was made
            $this->model_extension_payment_greenpay->insertCheckData(array(
                "order_id" => $order_info['order_id'],
                "customer_id" => $order_info['customer_id'],
                "eCommerceOrder_id" => "",
                "check_id" => "SK" . (string) $response->Unique_ID,
                "check_number" => ""
            ));

            $this->response->redirect($this->url->link('checkout/success', '', true));
        } else {
            if($debug){
                error_log("[GreenPay] Checkout failed due to null response or non-zero Result Code.");
            }
            $this->session->data['error'] = "GreenPay checkout failed with an error: " . ($response) ? (string)$response->Result->Description : "";
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    /**
     * Internal helper function called by the main checkout method that will handle calling the API with raw routing and account number 
     */
    private function checkout_manual($configuration){
        $debug = false;
        if (isset($configuration["greenpay_debug"]) && $configuration["greenpay_debug"]) {
            $debug = true;
        }

        if ($debug) {
            error_log("[GreenPay] Checkout process determined to be manual.");
        }

        if (!isset($this->request->post['greenpay_account_number']) || !isset($this->request->post['greenpay_routing_number'])) {
            // If either the account or routing numbers are unset, then we need to redirect back to the cart and show errors
            $this->session->data['error'] = $this->language->get('error_both_required');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $account_number = trim($this->request->post['greenpay_account_number']);
        $routing_number = trim($this->request->post['greenpay_routing_number']);

        if (strlen($account_number) == 0) {
            //Don't just check for existence of keys, but trim them and make sure they have values
            $this->session->data['error'] = $this->language->get("error_account_empty");
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        }

        if (strlen($routing_number) == 0) {
            $this->session->data['error'] = $this->language->get("error_routing_empty");
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        }

        if (!is_numeric($account_number)) {
            $this->session->data['error'] = "Account number cannot contain any non-numeric characters.";
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        }

        $routing_error = "";
        if (!$this->routing_number_validate($routing_number, $routing_error)) {
            $this->session->data['error'] = "Routing number failed validation: " . $routing_error;
            $this->response->redirect($this->url->link("checkout/checkout", "", true));
        }

        // Order info
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }

        $check = array(
            "Client_ID" => $configuration['greenpay_client_id'],
            "APIPassword" => $configuration['greenpay_api_password'],
            "StoreID" => $configuration["greenpay_store_id"],
            "OrderID" => $order_id,
            "RoutingNumber" => $routing_number,
            "AccountNumber" => $account_number
        );

        if ($debug) {
            error_log("[GreenPay] All validations passed. Sending data to API: \r\n" . print_r($check, true));
        }

        $response = $this->postGreenAPI("OneTimeDraft", $check);
        if ($response == null || !($response instanceof SimpleXMLElement)) {
            //Error occurred
            $this->session->data['error'] = "GreenPay Payment Error: " . $this->language->get('error_something_wrong');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        } else {
            //We've got the thing
            if ((string) $response->Result->Result == "0") {
                //Success, check was made
                $this->model_extension_payment_greenpay->insertCheckData(array(
                    "order_id" => $order_info['order_id'],
                    "customer_id" => $order_info['customer_id'],
                    "eCommerceOrder_id" => (string) $response->ECommerceOrder_ID,
                    "check_id" => (string) $response->Check_ID,
                    "check_number" => (string) $response->CheckNumber
                ));

                $this->response->redirect($this->url->link('checkout/success', '', true));
            } else {
                //Code was non-zero meaning something occurred. Display the ResultDescription
                $this->session->data["error"] = "GreenPay Payment Error: " . (string) $response->Result->ResultDescription;
                $this->response->redirect($this->url->link('checkout/checkout', '', true));
            }
        }
    }

    /**
     * Internal helper function to determine whether or not a routing number appears to be validate
     *
     * @param string $routing_number	The string version of the routing number to validate
     * @param string $error 			A reference to a string which will contain the error if it returns false
     *
     * @return bool Whether the routing number validates as either a US or a CA routing number
     */
    private function routing_number_validate($routing_number, &$error)
    {
        if (strlen($routing_number) !== 9) {
            $error = "Must be 9 digits.";
            return false;
        }

        if (ctype_digit($routing_number)) {
            //It's all numeric, so let's try to make sure it fits the US format
            if ($routing_number === "000000000" || $routing_number === "642260020") return true;
            if ((int) $routing_number > 370000000) {
                $error = "Doesn't match valid routing number format defined by the ABA.";
                return false;
            }

            $digits = array();
            foreach (str_split($routing_number) as $key => $char) {
                $digits[] = (int) $char;
            }

            $chk = ((7 * ($digits[0] + $digits[3] + $digits[6])) + (3 * ($digits[1] + $digits[4] + $digits[7])) + (9 * ($digits[2] + $digits[5]))) % 10;
            if (strcasecmp($chk, $digits[8]) !== 0) {
                $error = "Doesn't match valid routing number format defined by the ABA.";
                return false;
            }
            return true;
        } else {
            //It could still be a Canadian routing number
            $split = explode("-", $routing_number);
            if (count($split) !== 2) {
                $error = "Doesn't match valid routing number format for Canada.";
                return false;
            }

            if (!((strlen($split[0]) === 5) && (strlen($split[1]) === 3))) {
                $error = "Doesn't match valid routing number format for Canada.";
                return false;
            }
            return true;
        }
    }

    /**
     * Will call out to the API to load the Tokenizer Widget front end scripts that need to be injected into the page
     *
     * @param mixed $options The array of parameters to be sent to the API. Must contain a Client_ID, ApiPassword, Amount, Display, CustomerId, and CustomerData parameters
     * @throws Exception
     * @return mixed False if the load failed for any reason, otherwise the raw HTML to be injected
     */
    public function call_for_widget($options)
    {
        $this->load->model('extension/payment/greenpay');
        $greenConfig = $this->getGreenmoneyConfig();
        $debug = false;
        if (isset($greenConfig["greenpay_debug"]) && $greenConfig["greenpay_debug"]) {
            $debug = true;
        }

        $options["Client_ID"] = $greenConfig["greenpay_client_id"];
        $options["ApiPassword"] = $greenConfig["greenpay_api_password"];

        if ($debug) {
            error_log("[GreenPay] Beginning API call to " . GREENPAY_WEBSITE . "FTFTokenizer.asmx/GenerateWidget");
            error_log("[GreenPay] Raw data being sent: \r\n" . print_r($options, true));
        }

        try {
            $ch = curl_init();

            if($ch === FALSE){
                throw new \Exception('Failed to initialize cURL');
            }
            $data_string = json_encode($options);
            curl_setopt_array($ch, array(
                CURLOPT_URL => GREENPAY_WEBSITE . "FTFTokenizer.asmx/GenerateWidget",
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string)
                ),
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_POSTFIELDS => $data_string
            ));
            $response = curl_exec($ch);
            $response = json_decode($response)->d;
            if($response === FALSE || curl_errno($ch) !== 0 || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200){
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            curl_close($ch);
        }
        catch(\Exception $e) {
            error_log("[GreenPay] Exception occurered while attempting to decode response from API call: " . $e->getMessage());
            return false;
        }

        return $response;
    }

    /**
     * Returns the merchant's Tokenization service Merchant ID
     * @return string|bool	False on a failure or the authenticated merchant ID
     */
    public function widget_mid()
    {
        $this->load->model('extension/payment/greenpay');
        $greenConfig = $this->getGreenmoneyConfig();
        $options = array();
        $options["Client_ID"] = $greenConfig["greenpay_client_id"];
        $options["ApiPassword"] = $greenConfig["greenpay_api_password"];

        $response = $this->postGreenAPI("TokenizerMID", $options, "FTFTokenizer.asmx");
        if($response === null){
            return false;
        }
        return $response;
    }
    
    /**
     * Make a call to the Green API with the given data
     * 
     * @param string $messageName       The method at the API endpoint to be called
     * @param mixed $data               Either string or array. If given, will be added as a CURLOPT_POSTFIELDS to the request
     * @param string $method            Optional. If supplied, will override the method name in the endpoint URL.
     * @return SimpleXMLElement|null    The XML object returned by the API read into an array by simplexml library or null on error
     */
    private function postGreenAPI($messageName, $data, $method = null)
    {
        $greenConfig = $this->getGreenmoneyConfig();
        $debug = false;
        if (isset($greenConfig["greenpay_debug"]) && $greenConfig["greenpay_debug"]) {
            $debug = true;
        }

        $link = GREENPAY_ENDPOINT . $messageName;
        if($method !== null){
            $link = GREENPAY_WEBSITE . $method . "/" . $messageName;
        }

        if ($debug) {
            error_log("[GreenPay] Beginning API call to " . $link);
            error_log("[GreenPay] Raw data being sent: \r\n" . print_r($data, true));
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if (isset($data)) {
            $params = array();
            foreach ($data as $key => $value) {
                $params[] = $key . "=" . urlencode($value);
            }

            if ($debug) {
                error_log("[GreenPay] Query string: " . implode("&", $params));
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $params));
        }

        $response = null;
        try {
            $result = curl_exec($ch);
            $response = @simplexml_load_string($result); //@ specifies to ignore warnings thrown by this attempt to load the XML into an object

            if ($debug) {
                error_log("[GreenPay] Raw response from API: \r\n" . $result);
                error_log("[GreenPay] Decoded response from API: \r\n" . print_r($response, true));
            }
        } catch (Exception $e) {
            // Redirect to the cart and display error
            error_log("[GreenPay] Exception occurered while attempting to decode response from API call: " . $e->getMessage());
            $this->lastAPIError = $e->getMessage();
        } finally {
            curl_close($ch);
        }

        return $response;
    }
}
