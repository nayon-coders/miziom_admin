<?php

namespace App\Http\Controllers\admin;

use App\Http\Requests\Admin\CoinRequest;
use App\Http\Requests\Admin\CoinSaveRequest;
use App\Http\Requests\Admin\CoinSettingRequest;
use App\Http\Requests\Admin\WebhookRequest;
use App\Http\Requests\UpdateWalletKeyRequest;
use App\Http\Services\CoinService;
use App\Http\Services\CoinSettingService;
use App\Http\Services\CurrencyService;
use App\Http\Services\Logger;
use App\Models\Coin;
use App\Models\CurrencyList;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class CoinController extends Controller
{
    private $coinService;
    private $coinSettingService;
    private $logger;
    public function __construct()
    {
        $this->coinService = new CoinService();
        $this->logger = new Logger();
        $this->coinSettingService = new CoinSettingService();
    }

    // all coin list
    public function adminCoinList()
    {
        try {
            $data['title'] = __('Coin List');
            $data['coins'] = Coin::where('status', '<>', STATUS_DELETED)->get();
            return view('coin-order.coin', $data);
        } catch (\Exception $e) {
            $this->logger->log('admin coin-list exception --> ',$e->getMessage());
            return redirect()->back()->with('dismiss', $e->getMessage());
        }
    }

    // change coin status
    public function adminCoinStatus(Request $request)
    {
        $coin = Coin::find($request->active_id);
        if ($coin) {
            if ($coin->status == STATUS_ACTIVE) {
               $coin->update(['status' => STATUS_DEACTIVE]);
            } else {
                $coin->update(['status' => STATUS_ACTIVE]);
            }
            return response()->json(['message'=>__('Status changed successfully')]);
        } else {
            return response()->json(['message'=>__('Coin not found')]);
        }
    }

    // edit coin
    public function adminCoinEdit($id)
    {
        $coinId = decryptId($id);

        if(is_array($coinId)) {
            return redirect()->back()->with(['dismiss' => __('Coin not found')]);
        }

        $item = $this->coinService->getCoinDetailsById($coinId);

        if (isset($item) && $item['success'] == false) {
            return redirect()->back()->with(['dismiss' => $item['message']]);
        }

        $data['item'] = $item['data'];
        $data['title'] = __('Update Coin');
        $data['button_title'] = __('Update');

        return view('coin-order.edit-coin', $data);
    }


//    coin save process
    public function adminCoinSaveProcess(CoinRequest $request)
    {
        try {
            $coin_id = '';
            $input['coin_type'] = $request->coin_type;
            $input['network'] = $request->network;
            $input['name'] = $request->name;
            $input['usd_rate'] = $request->coin_price;
            $input['is_deposit'] = isset($request->is_deposit) ? 1 : 0;
            $input['is_withdrawal'] = isset($request->is_withdrawal) ? 1 : 0;
            $input['status'] = isset($request->status) ? 1 : 0;
            $input['minimum_withdrawal'] = $request->minimum_withdrawal;
            $input['maximum_withdrawal'] = $request->maximum_withdrawal;
            $input['withdrawal_fees'] = $request->withdrawal_fees;
            $input['max_send_limit'] = $request->max_send_limit ?? 0;
            $input['withdrawal_fees_type'] = $request->withdrawal_fees_type ?? 2;

            if (!empty($request->coin_icon)) {
                $icon = uploadImage($request->coin_icon,IMG_ICON_PATH,'');
                if ($icon != false) {
                    $input['coin_icon'] = $icon;
                }
            }

            if($request->coin_id) {
                $coin_id = decryptId($request->coin_id);
            }

            $coin = $this->coinService->addCoin($input, $coin_id);

            return (isset($coin) && $coin['success']) ? redirect()->back()->with(['success' => $coin['message']]) :
                redirect()->back()->with(['dismiss' => $coin['message']]);
        } catch (\Exception $e) {
            storeException('adminCoinSaveProcess', $e->getMessage());
            redirect()->back()->with(['dismiss' => __('Something went wrong')]);
        }
    }

    // add coin page
    public function adminAddCoin()
    {
        $data['title'] = __('Add New Coin');
        $data['button_title'] = __('Save');
        $data['currency'] = CurrencyList::whereStatus(STATUS_ACTIVE)->get();
        return view('coin-order.add-coin', $data);
    }

    // admin new coin save process
    public function adminSaveCoin(CoinSaveRequest $request)
    {
        try {
            $data = [
                'currency_type' => $request->currency_type,
                'name' => $request->name,
                'coin_type' => strtoupper($request->coin_type),
                'network' => $request->network,
            ];
            if($request->currency_type == CURRENCY_TYPE_FIAT){
                if($currency = CurrencyList::whereCode($request->coin_type)->first()){
                    $data['currency_id'] = $currency->id;
                    $data['usd_rate'] = bcdiv(1,$currency->rate,8);
                }
            } else {
                if (isset($request->get_price_api) && $request->get_price_api == 1) {
                    $pair = strtoupper($request->coin_type).'_'.'USDT';
                    $apiData = getPriceFromApi($pair);
                    if ($apiData['success'] == true) {
                        $data['usd_rate'] = $apiData['data']['price'];
                    } else {
                        return redirect()->back()->with('dismiss', __('Get api data failed, please add manual price'));
                    }
                } else {
                    $data['usd_rate'] = $request->coin_price;
                }
            }

            if (Coin::create($data))
                return redirect()->route('adminCoinList')->with('success', __('New coin added successfully'));
            return redirect()->back()->with('dismiss', __('Something went wrong'));
        } catch (\Exception $e) {
            $this->logger->log('adminSaveCoin : ',$e->getMessage());
            return redirect()->back()->with('dismiss', $e->getMessage().__('Something went wrong'));
        }
    }

    // edit coin settings
    public function adminCoinSettings($id)
    {
        try {
            $coinId = decryptId($id);
            if(is_array($coinId)) {
                return redirect()->back()->with(['dismiss' => __('Coin not found')]);
            }
            $item = $this->coinSettingService->getCoinSettings($coinId);
            if (isset($item) && $item['success'] == false) {
                return redirect()->back()->with(['dismiss' => $item['message']]);
            }
            $data['item'] = $item['data'];
            $data['title'] = __('Update Coin Setting');
            $data['button_title'] = __('Update Setting');

            if ($item['data']->network == COIN_PAYMENT) {
                return redirect()->route('adminCoinApiSettings', ['tab' => 'payment']);
            } else {
                return view('coin-order.edit_coin_settings', $data);
            }
        } catch (\Exception $e) {
            storeException('adminCoinSettings',$e->getMessage());
            return redirect()->back()->with('dismiss',__('Something went wrong'));
        }
    }

    // admin save coin setting
    public function adminSaveCoinSetting(CoinSettingRequest $request)
    {
        try {
            $response = $this->coinSettingService->updateCoinSetting($request);
            if ($response['success'] == true) {
                return redirect()->back()->with('success', $response['message']);
            } else {
                return redirect()->back()->with('dismiss', $response['message']);
            }
        } catch (\Exception $e) {
            storeException('adminSaveCoinSetting', $e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong'));
        }
    }

    // admin bitgo wallet adjust
    public function adminAdjustBitgoWallet($id)
    {

        try {
            $coinId = decryptId($id);
            if(is_array($coinId)) {
                return redirect()->back()->with(['dismiss' => __('Coin not found')]);
            }
            $response = $this->coinSettingService->adjustBitgoWallet($coinId);
            if ($response['success'] == true) {
                return redirect()->back()->with('success', $response['message']);
            } else {
                return redirect()->back()->with('dismiss', $response['message']);
            }
        } catch (\Exception $e) {
            storeException('adminAdjustBitgoWallet', $e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong'));
        }
    }

    public function adminCoinRate(){

        $currency = new CurrencyService();
        $response = $currency->updateCoinRate();
        if($response["success"])
            return redirect()->back()->with("success",$response["message"]);
        return redirect()->back()->with("dismiss",$response["message"]);
    }

    // admin coin delete
    public function adminCoinDelete($id)
    {

        try {
            $coinId = decryptId($id);
            if(is_array($coinId)) {
                return redirect()->back()->with(['dismiss' => __('Coin not found')]);
            }
            $response = $this->coinService->adminCoinDeleteProcess($coinId);
            if ($response['success'] == true) {
                return redirect()->back()->with('success', $response['message']);
            } else {
                return redirect()->back()->with('dismiss', $response['message']);
            }
        } catch (\Exception $e) {
            storeException('adminCoinDelete', $e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong'));
        }
    }

    // admin user coin
    public function adminUserCoinList()
    {
        $data['title'] = __('User Total Coin Amount');
        $data['items'] = Wallet::join('coins','coins.id','=','wallets.coin_id')
            ->where(['coins.status' => STATUS_ACTIVE])
            ->selectRaw('sum(wallets.balance) as total_balance, coins.coin_type, coins.name')
            ->groupBy('coins.id')
            ->get();

        return view('admin.coin-order.user_coin', $data);
    }

    public function webhookSave(WebhookRequest $request)
    {
        try {
            $response = $this->coinService->webhookSaveProcess($request);
            if ($response['success'] == true) {
                return redirect()->back()->with('success', $response['message']);
            } else {
                return redirect()->back()->with('dismiss', $response['message']);
            }
        } catch (\Exception $e) {
            storeException('webhookSave: ', $e->getMessage());
            return redirect()->back()->with('dismiss', __('Something went wrong'));
        }
    }

    public function check_wallet_address(Request $request)
    {
        try {
            if(!isset($request->coin_type))
            {
                $response = ['success'=>false, 'message'=>__('Coin type is missing!')];
                return response()->json($response);
            }
            if(!isset($request->wallet_key))
            {
                $response = ['success'=>false, 'message'=>__('Wallet key is missing!')];
                return response()->json($response);
            }
            $coin_type = $request->coin_type;

            $coin = Coin::join('coin_settings','coin_settings.coin_id', '=', 'coins.id')
                        ->where(['coins.coin_type' => $coin_type])
                        ->first();

            if(isset($coin))
            {
                $api = new ERC20TokenApi($coin);
                $requestData =['contracts' => $request->wallet_key];
                $result = $api->getAddressFromPK($requestData);
                if($result['success'])
                {
                    $response = ['success'=>true, 'message'=>__('Your wallet address: ').$result['data']->address];
                }else{
                    $response = ['success'=>false, 'message'=>$result['message']];
                }
            }else{
                $response = ['success'=>false, 'message'=>__('Invalid Request: ')];
            }
            return response()->json($response);

        } catch (\Exception $e) {
            storeException('webhookSave: ', $e->getMessage());
            $response = ['success'=>false, 'message'=>__('Something went wrong')];
            return response()->json($response);
        }
    }

    public function coinMakeListed($id)
    {
        $response = $this->coinService->makeTokenListedToCoin(decrypt($id));
        if($response['success'])
        {
            return back()->with(['success'=>$response['message']]);
        }else{
            return back()->with(['dismiss'=>$response['message']]);
        }

    }

    public function updateWalletKey(UpdateWalletKeyRequest $request)
    {
        $response = $this->coinService->updateWalletKey($request);

        if($response['success'])
        {
            return back()->with(['success'=>$response['message']]);
        }else{
            return back()->with(['dismiss'=>$response['message']]);
        }
    }

    public function viewWalletKey(Request $request)
    {
        $response = $this->coinService->viewWalletKey($request);
        return response()->json($response);
    }

    public function demoTradeCoinStatus($coin_type)
    {
        try {
            if($coin = Coin::whereCoinType($coin_type)->first()){
                if($coin->update(['is_demo_trade' => (! $coin->is_demo_trade)]))
                    return response()->json(responseData(true, __("Coin status updated")));
                return response()->json(responseData(false, __("Coin not updated")));
            }
            return response()->json(responseData(false, __("Coin not found")));
        } catch (\Exception $e) {
            storeException('demoTradeCoinStatus', $e->getMessage());
            return response()->json(responseData(false, __("Something went wrong")));
        }
    }
}