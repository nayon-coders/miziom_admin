<?php

namespace App\Http\Services;

use App\Models\Coin;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use App\Models\WalletAddressHistory;
use Illuminate\Support\Facades\Auth;
use App\Http\Repository\WalletRepository;
use App\Http\Services\BitgoWalletService;

class WalletService extends BaseService
{
    public $repository;
    public function __construct()
    {
        $this->repository =  new WalletRepository();
        parent::__construct($this->repository);

    }

    // coin data
   public function coinList($type) {
        try {
            if($type == CURRENCY_TYPE_BOTH) {
                $items = Coin::where(['status' => STATUS_ACTIVE])->get();
            } else {
                $items = Coin::where(['status' => STATUS_ACTIVE, 'currency_type' => $type])->get();
            }
            if (isset($items[0])) {
                return responseData(true, __('Data get successfully'),$items);
            } else {
                return responseData(false, __('Data not found'));
            }
        } catch(\Exception $e) {
            storeException('coin list ex', $e->getMessage());
            return responseData(false, __('Something went wrong'));
        }
    }

     // user wallet list
   public function userWalletList($userId,$type,$currency) {
     try {
            createUserWallet($userId);
            $items = $this->repository->getUserWalletList($userId,$type,$currency);
            if (isset($items[0])) {
                return responseData(true, __('Data get successfully'),$items);
            } else {
                return responseData(false, __('Data not found'));
            }
        } catch(\Exception $e) {
            storeException('userWalletList ex', $e->getMessage());
            return responseData(false, __('Something went wrong'));
        }
    }

// save data
public function saveItemData($request)
{
    $response = responseData(false);
    try {
        $item='';
        if($request->edit_id) {
            $data = $request->except(['_token', 'edit_id','actions']);
            $item = $this->repository->whereFirst(['unique_code' => $request->edit_id]);
            if(empty($item)) {
                return responseData(false,__('Data not found'));
            }
        } else {
            $data = $request->except(['_token','actions']);
            $data['unique_code'] = makeUniqueId();
        }
        if(isset($request->actions[0])){
            $data['actions'] = implode('|', $request->actions);
        } else {
            return responseData(false,__('Must be select atleast one role'));
        }
        if($request->edit_id) {
            $this->repository->update(['id' => $item->id], $data);
            $response = responseData(true,__('Role updated successfully'));
        } else {
            $this->repository->create($data);
            $response = responseData(true,__('New role added successfully'));
        }
    } catch (\Exception $e) {
        
        storeException('save role', $e->getMessage());
    }
    return $response;
}

    public function searcheCustomer($request) {
        try {
            if(!isset($request->search) && empty($request->search))
                return responseData(false, __('Customer not found!'));

            $search = $request->search;
            $user = User::where(function($q)use($search){
                        return $q->where('name', 'LIKE', "%$search%")
                        ->orWhere('email', 'LIKE', "%$search%");
                    })
                    ->where('role_module', MODULE_USER)
                    ->get(['unique_code', 'email']);
            $data['customer'] = $user;

            if(isset($user[0])) return responseData(true, __('Customer get successfully'),$data);
            return responseData(false, __('Customer not found!')); 
        } catch(\Exception $e) {
            storeException('searcheCustomer ex', $e->getMessage());
            return responseData(false, __('Something went wrong'));
        }
    }
   
    public function walletWithdrawal($request, $currency) {
        try {
            // check coin type exist
            if(!isset($request->coin_type))
                return responseData(false, __('Coin not found!')); 

            // set coin type in variable
            $coin_type = $request->coin_type;

            $wallet = DB::table('wallets')
                        ->join('coins', 'coins.id', '=', 'wallets.coin_id')
                        ->where('coins.coin_type', '=', $coin_type)
                        ->where('coins.status', '=', STATUS_ACTIVE)
                        ->select('wallets.*', 'coins.*')
                        ->first();
                        
            $rate = convert_currency($wallet->coin_type,$currency,1);
            $wallet->usd_value_rate = $rate;
            $wallet->usd_value = bcmul($rate,$wallet->balance,8);
            $data['wallet'] = $wallet;

            return responseData(true, __('Withdrawal data found!'), $data);
        } catch(\Exception $e) {
            storeException('searcheCustomer ex', $e->getMessage());
            return responseData(false, __('Something went wrong'));
        }
    }

    public function walletDeposit($request, $currency) {
        try {
            // check coin type exist
            if(!isset($request->coin_type))
                return responseData(false, __('Coin type not found!')); 

            // set coin type in variable
            $coin_type = $request->coin_type;

            if(! $coin = Coin::where(['coin_type' => $coin_type, 'status' => STATUS_ACTIVE])->first())
                return responseData(false, __('Coin not found!'));
            
            if(! $wallet = Wallet::where(['coin_id' => $coin->id, 'user_id' => Auth::id()])->first(['coin_type','balance','referral_balance']))
                return responseData(false, __('Wallet not found!'));
            
            
            if(! $walletAddress = WalletAddressHistory::where(['wallet_id' => $wallet->id])->first())
            {
                // TODO: wallet address generation
            }
            $wallet->is_withdrawal        = $coin->is_withdrawal;
            $wallet->minimum_withdrawal   = $coin->minimum_withdrawal;
            $wallet->maximum_withdrawal   = $coin->maximum_withdrawal;
            $wallet->max_send_limit       = $coin->max_send_limit;
            $wallet->withdrawal_fees      = $coin->withdrawal_fees;
            $wallet->withdrawal_fees_type = $coin->withdrawal_fees_type;
            $rate                         = convert_currency($wallet->coin_type,$currency,1);
            $wallet->usd_value_rate       = $rate;
            $wallet->usd_value            = bcmul($rate,$wallet->balance,8);
            $wallet->address              = $walletAddress->address ?? 'thisisademoaddress'; 
            $data['wallet'] = $wallet;
            $data['memo'] = null;
            
            return responseData(true, __('Wallet deposit data found!'), $data);
        } catch(\Exception $e) {
            storeException('searcheCustomer ex', $e->getMessage());
            return responseData(false, __('Something went wrong'));
        }
    }

    function get_bitgo_address($coin_type)
    {
        try {
            $coin = Coin::join('coin_settings','coin_settings.coin_id', '=', 'coins.id')
                ->where(['coins.coin_type' => $coin_type])
                ->first();
            if ($coin) {
                if (empty($coin->bitgo_wallet_id)) {
                    storeException('get_bitgo_address ', 'bitgo_wallet_id not found');
                    return false;
                } else {
                    $bitgoApi =  new BitgoWalletService();
                    $address = $bitgoApi->createBitgoWalletAddress($coin->coin_type,$coin->bitgo_wallet_id,$coin->chain);
                    storeException('address bitgo', json_encode($address));
                    if ($address['success']) {
                        return $address['data']['address'];
                    } else {
                        storeException('get_bitgo_address address', $address['message']);
                        return false;
                    }
                }
            } else {
                storeException('get_bitgo_address ', ' Coin not found');
                return false;
            }
        } catch (\Exception $e) {
            storeException('get_bitgo_address ', $e->getMessage());
        }
    }

    public function walletWithdrawalProccess($request)
    {
        $user = Auth::user();
        $wallet = Wallet::join('coins', 'coins.id', '=', 'wallets.coin_id')
                ->where(['wallets.id'=>$request->wallet_id, 'wallets.user_id'=> $user->id, 'coins.is_withdrawal' => STATUS_ACTIVE])
                ->select('wallets.*', 'coins.status as coin_status', 'coins.is_withdrawal', 'coins.minimum_withdrawal',
                    'coins.maximum_withdrawal', 'coins.withdrawal_fees', 'coins.max_send_limit','coins.withdrawal_fees_type','coins.network')
                ->first();

        if(!$wallet) return responseData(false, __('Wallet not found!'));

        $amount = $request->amount;

        if($request->type == WITHDRAWAL_CURRENCY_TYPE_CRYPTO){

            if( !($wallet->balance >= $amount))
                return responseData(false, __('insufficient balance for withdrawal request!'));
        }else{

            $usdTotalAmount = convert_currency($wallet->coin_type,'USD',1);
            if( !($usdTotalAmount >= $amount))
                return responseData(false, __('insufficient balance for withdrawal request!'));
        }

        if(isset($request->customer_id)){
            if(! $customer = User::find($request->customer_id))
                return responseData(false, __('Customer not found!'));

            if(! $customerWallet = Wallet::where(['user_id' => $customer->id, "coin_type" => $wallet->coin_type])->first()){
                createUserWallet($customer->id);
                $customerWallet = Wallet::where(['user_id' => $customer->id, "coin_type" => $wallet->coin_type])->first();
            }
            
            return responseData(true, __("Customer withdrawal success"));
        }

        $withdrawl_type = ADDRESS_TYPE_INTERNAL;
        if(! $addressHistory = WalletAddressHistory::where('address', $request->address)->first()){
            $withdrawl_type = ADDRESS_TYPE_EXTERNAL;
            return responseData(true, __("EXTERNAL withdrawal success"));
        }

        return responseData(true, __("INTERNAL withdrawal success"));
    }

}
