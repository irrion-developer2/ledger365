<?php

namespace App\Http\Controllers\DesktopApp\Import;

use DB;
use Carbon\Carbon;
use App\Models\TallyItem;
use App\Models\TallyUnit;
use App\Models\TallyGodown;
use App\Models\TallyLedger;
use App\Models\TallyCompany;
use App\Models\TallyLicense;
use App\Models\TallyVoucher;
use Illuminate\Http\Request;
use App\Models\TallyCurrency;
use App\Models\TallyItemGroup;
use App\Models\TallyLedgerGroup;
use App\Models\TallyVoucherHead;
use App\Models\TallyVoucherItem;
use App\Models\TallyVoucherType;
use App\Models\TallyBankAllocation;
use App\Models\TallyBillAllocation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\TallyBatchAllocation;

class LedgerController extends Controller
{
    private function extractNumericValue($string)
    {
        if ($string) {
            $string = trim($string);
            if (preg_match('/-?\d+(\.\d+)?/', $string, $matches)) {
                return (float)$matches[0];
            }
        }
        return null;
    }

    private function ensureArray($data)
    {
        if (is_array($data)) {
            return $data;
        }

        return !empty($data) ? [$data] : [];
    }

    private function normalizeEntries($entries)
    {
        if (is_object($entries)) {
            $entries = (array)$entries;
        }

        if (is_array($entries)) {
            foreach ($entries as &$entry) {
                if (is_object($entry)) {
                    $entry = (array)$entry;
                }
            }
            return empty($entries) || isset($entries[0]) ? $entries : [$entries];
        }

        return [];
    }

    private function convertToDesiredDateFormat($date)
    {
        if (preg_match('/^\d{8}$/', $date)) {
            $dateObject = \DateTime::createFromFormat('Ymd', $date);
            return $dateObject ? $dateObject->format('d-M-y') : $date; // Example: '25-Aug-24'
        }
        return $date;
    }

    private function findTallyMessage($jsonArray, $path = '')
    {
        foreach ($jsonArray as $key => $value) {
            $currentPath = $path ? $path . '.' . $key : $key;
            if ($key === 'TALLYMESSAGE') {
                return ['path' => $currentPath, 'value' => $value];
            }
            if (is_array($value)) {
                $result = $this->findTallyMessage($value, $currentPath);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }

    private function validateLicenseNumber(Request $request)
    {
        $licenseNumber = $request->input('license_number');
        if (empty($licenseNumber)) {
            Log::info('license number is required');
            throw new \Exception('license number ');
        }

        $license = TallyLicense::where('license_number', $licenseNumber)->first();
        if (!$license) {
            Log::info('License not found for license number: ' . $licenseNumber);
            throw new \Exception('License not found');
        } elseif ($license->status != 1) {
            Log::info('License not active for license number: ' . $licenseNumber);
            throw new \Exception('License not active');
        }
    }

    private function licenseCheckJsonImport(Request $request)
    {
        $request->validate([
            'license_number' => 'required|string',
        ]);

        $licenseNumber = $request->input('license_number');

        $license = TallyLicense::where('license_number', $licenseNumber)->first();

        if (!$license) {
            return response()->json(['error' => 'License not found'], 404);
        } elseif ($license->status != 1) {
            return response()->json(['error' => 'License not active'], 403);
        }

        return response()->json(['message' => 'License is valid and active'], 200);
    }

    public function masterJsonImport(Request $request)
    {
        try {

            // Log::info('masterJsonImport function called');

            $this->validateLicenseNumber($request);

            $jsonData = null;
            $fileName = 'tally_master_data_' . date('YmdHis') . '.json';


            if ($request->hasFile('uploadFile')) {
                $uploadedFile = $request->file('uploadFile');
                $jsonFilePath = storage_path('app/' . $fileName);

                $uploadedFile->move(storage_path('app'), $fileName);
                $jsonData = file_get_contents($jsonFilePath);

            } else {
                $jsonData = $request->getContent();
                $jsonFilePath = storage_path('app/' . $fileName);
                file_put_contents($jsonFilePath, $jsonData);
            }

            $data = json_decode($jsonData, true);
            $result = $this->findTallyMessage($data);

            if ($result === null) {
                throw new \Exception('TALLYMESSAGE key not found in the JSON data.');
            }

            $messagesPath = $result['path'];
            $messages = $result['value'];

            $currencyCount = 0;
            $groupCount = 0;
            $ledgerCount = 0;
            $companyIds = [];


            foreach ($messages as $message) {
                if (isset($message['CURRENCY'])) {
                    $currencyData = $message['CURRENCY'];
                    // Log::info('Currency Data:', ['currencyData' => $currencyData]);

                    $guid = $currencyData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;

                    $tallyCurrency = TallyCurrency::updateOrCreate(
                        ['currency_guid' => $currencyData['GUID'] ?? null,],
                        [
                            'alter_id' => $currencyData['ALTERID'] ?? null,
                            'company_id' => $companyId,
                            'currency_name' => $currencyData['MAILINGNAME'] ?? null,
                            'symbol' => $currencyData['EXPANDEDSYMBOL'] ?? null,
                            'decimal_symbol' => $currencyData['DECIMALSYMBOL'] ?? null,
                            'decimal_places' => $currencyData['DECIMALPLACES'] ?? null,
                        ]
                    );

                    if ($tallyCurrency) {
                        $currencyCount++;
                    }

                    if (!$tallyCurrency) {
                        $currencyCount++;
                        throw new \Exception('Failed to create or update tally ledger Currency record.');
                    }
                }
            }

            foreach ($messages as $message) {
                if (isset($message['GROUP'])) {
                    $groupData = $message['GROUP'];
                    // Log::info('Group Data:', ['groupData' => $groupData]);

                    $nameField = $groupData['LANGUAGENAME.LIST']['NAME.LIST']['NAME'] ?? null;
                    if (is_array($nameField)) {
                        $nameField = implode(', ', $nameField);
                    }

                    $guid = $groupData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;
                    $companyIds[$companyId] = true;

                    $parent = $groupData['PARENT'] ?? null;
                    $isPrimary = empty($parent);


                    $tallyLedgerGroup = TallyLedgerGroup::updateOrCreate(
                        ['ledger_group_guid' => $guid],
                        [
                            'company_id' => $companyId,
                            'parent' => $groupData['PARENT'] ?? null,
                            'affects_stock' => isset($groupData['AFFECTSSTOCK']) && $groupData['AFFECTSSTOCK'] === 'Yes',
                            'alter_id' => $groupData['ALTERID'] ?? null,
                            'ledger_group_name' => $groupData['NAME'] ?? null,
                            'is_primary' => $isPrimary,
                        ]
                    );


                    if ($tallyLedgerGroup) {
                        $groupCount++;
                    }
                    if (!$tallyLedgerGroup) {
                        throw new \Exception('Failed to create or update tally ledger group record.');
                    }

                }
            }

            // After processing all groups, update the primary_group field
            // Log::info('Updating primary_group field for all groups');
            foreach (array_keys($companyIds) as $companyId) {
                $groups = TallyLedgerGroup::where('company_id', $companyId)->get();
                // Log::info($companyId . ' - Found ' . count($groups) . ' groups');
                // Build associative array of groups by name
                $groupsByName = [];
                foreach ($groups as $group) {
                    $groupsByName[$group->ledger_group_name] = $group;
                }

                foreach ($groups as $group) {
                    $primaryGroupName = $this->getPrimaryGroup($group, $groupsByName);
                    $group->primary_group = $primaryGroupName;
                    $group->save();
                    // Log::info('Updated primary_group for group', ['group_id' => $group->id, 'primary_group' => $primaryGroupName]);
                }
            }

            foreach ($messages as $message) {
                if (isset($message['LEDGER'])) {
                    $ledgerData = $message['LEDGER'];
                    // Log::info('Ledger Data:', ['ledgerData' => $ledgerData]);

                    $applicableFrom = null;
                    if (isset($ledgerData['LEDGSTREGDETAILS.LIST']['APPLICABLEFROM'])) {
                        $applicableFrom = Carbon::createFromFormat('Ymd', $ledgerData['LEDGSTREGDETAILS.LIST']['APPLICABLEFROM'])->format('Y-m-d');
                    }

                    $addressList = $ledgerData['LEDMAILINGDETAILS.LIST']['ADDRESS.LIST']['ADDRESS'] ?? null;
                    if (is_array($addressList)) {
                        $addressList = implode(', ', $addressList);
                    }

                    $mailingApplicableFrom = null;
                    if (isset($ledgerData['LEDMAILINGDETAILS.LIST']['APPLICABLEFROM'])) {
                        $mailingApplicableFrom = Carbon::createFromFormat('Ymd', $ledgerData['LEDMAILINGDETAILS.LIST']['APPLICABLEFROM'])->format('Y-m-d');
                    }

                    $guid = $ledgerData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;

                    $nameField = $ledgerData['LANGUAGENAME.LIST']['NAME.LIST']['NAME'] ?? [];
                    $aliases = [];
                    if (is_array($nameField)) {
                        $languageName = $nameField[0] ?? null;
                        for ($i = 1; $i < count($nameField); $i++) {
                            $aliases[] = $nameField[$i] ?? null;
                        }
                    } else {
                        $languageName = $nameField;
                    }

                    $alias1 = $aliases[0] ?? null;
                    $alias2 = $aliases[1] ?? null;
                    $alias3 = $aliases[2] ?? null;

                    $parent = $ledgerData['PARENT'] ?? null;
                    $ledgerGroup = TallyLedgerGroup::where('ledger_group_name', $parent)
                        ->where('company_id', $companyId)
                        ->first();
                    $ledgerGroupId = $ledgerGroup ? $ledgerGroup->ledger_group_id : null;

                    $pincode = null;
                    if (isset($ledgerData['LEDMAILINGDETAILS.LIST']['PINCODE'])) {
                        $pincodeValue = $ledgerData['LEDMAILINGDETAILS.LIST']['PINCODE'];
                        if (ctype_digit($pincodeValue)) {
                            $pincode = $pincodeValue;
                        }
                    }

                    $party_gst_in = null;
                    if (isset($ledgerData['PARTYGSTIN'])) {
                        $partyGstIn = $ledgerData['PARTYGSTIN'];
                        if (strlen($partyGstIn) == 15) {
                            $party_gst_in = $partyGstIn;
                        }
                    }

                    $gst_in = null;
                    if (isset($ledgerData['LEDGSTREGDETAILS.LIST']['GSTIN'])) {
                        $gstIn = $ledgerData['LEDGSTREGDETAILS.LIST']['GSTIN'];
                        if (strlen($gstIn) == 15) {
                            $gst_in = $gstIn;
                        }
                    }

                    $email = $ledgerData['EMAIL'] ?? null;

                    if (is_array($email)) {
                        Log::info('Email is an array', ['email' => $email]);
                        // Extract the value with the empty string key
                        $email = $email[""] ?? null;
                        Log::info('Extracted email value', ['email' => $email]);
                    }

                    if (is_string($email) && strlen($email) > 255) {
                        $email = substr($email, 0, 255);
                    } else {
                        $email = "";
                    }

                    try {
                        $tallyLedger = TallyLedger::updateOrCreate(
                            ['ledger_guid' => $guid],
                            [
                                'company_id' => $companyId,
                                'ledger_group_id' => $ledgerGroupId,
                                'alter_id' => $ledgerData['ALTERID'] ?? null,
                                'ledger_name' => $languageName,
                                'alias1' => $alias1,
                                'alias2' => $alias2,
                                'alias3' => $alias3,
                                'parent' => $ledgerData['PARENT'] ?? null,
                                'tax_classification_name' => html_entity_decode($ledgerData['TAXCLASSIFICATIONNAME'] ?? null),
                                'tax_type' => $ledgerData['TAXTYPE'] ?? null,
                                'bill_credit_period' => $ledgerData['BILLCREDITPERIOD'] ?? null,
                                'credit_limit' => !empty($ledgerData['CREDITLIMIT']) ? $ledgerData['CREDITLIMIT'] : null,
                                'gst_type' => html_entity_decode($ledgerData['GSTTYPE'] ?? null),
                                'party_gst_in' => $party_gst_in,
                                'gst_duty_head' => $ledgerData['GSTDUTYHEAD'] ?? null,
                                'service_category' => html_entity_decode($ledgerData['SERVICECATEGORY'] ?? null),
                                'gst_registration_type' => $ledgerData['GSTREGISTRATIONTYPE'] ?? null,
                                'excise_ledger_classification' => html_entity_decode($ledgerData['EXCISELEDGERCLASSIFICATION'] ?? null),
                                'excise_duty_type' => html_entity_decode($ledgerData['EXCISEDUTYTYPE'] ?? null),
                                'excise_nature_of_purchase' => html_entity_decode($ledgerData['EXCISENATUREOFPURCHASE'] ?? null),
                                'is_bill_wise_on' => isset($ledgerData['ISBILLWISEON']) && $ledgerData['ISBILLWISEON'] === 'Yes',
                                'is_cost_centres_on' => isset($ledgerData['ISCOSTCENTRESON']) && $ledgerData['ISCOSTCENTRESON'] === 'Yes',
                                'opening_balance' => !empty($ledgerData['OPENINGBALANCE']) ? $ledgerData['OPENINGBALANCE'] : null,
                                'applicable_from' => $applicableFrom,
                                'ledger_gst_registration_type' => $ledgerData['LEDGSTREGDETAILS.LIST']['GSTREGISTRATIONTYPE'] ?? null,
                                'gst_in' => $gst_in,
                                'email' => $email,
                                'phone_number' => substr($ledgerData['LEDGERMOBILE'] ?? null, 0, 20),
                                'mailing_applicable_from' => $mailingApplicableFrom,
                                'pincode' => $pincode,
                                'mailing_name' => html_entity_decode($ledgerData['LEDMAILINGDETAILS.LIST']['MAILINGNAME'] ?? null),
                                'address' => $addressList,
                                'state' => html_entity_decode($ledgerData['LEDMAILINGDETAILS.LIST']['STATE'] ?? null),
                                'country' => html_entity_decode($ledgerData['LEDMAILINGDETAILS.LIST']['COUNTRY'] ?? null),
                            ]
                        );
                        if ($tallyLedger) {
                            $ledgerCount++;
                        }
                    } catch (\Exception $e) {
                        // log in detail
                        Log::error('Error creating ledger record: ' . $e->getMessage(), [
                            'ledgerData' => $ledgerData,
                            'companyGuid' => $companyGuid,
                            'companyId' => $companyId,
                        ]);
                    }

                    /*if (!$tallyLedger) {
                        throw new \Exception('Failed to create or update tally ledger record.');
                    }*/
                }
            }

            return response()->json([
                'message' => 'Master Saved',
                'currencies_inserted' => $currencyCount,
                'groups_inserted' => $groupCount,
                'ledgers_inserted' => $ledgerCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error importing data: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getPrimaryGroup($group, $groupsByName)
    {
        $visited = [];
        while ($group->parent) {
            if (isset($visited[$group->ledger_group_name])) {
                // Circular reference detected
                break;
            }
            $visited[$group->ledger_group_name] = true;

            if (!isset($groupsByName[$group->parent])) {
                // Parent group not found
                break;
            }
            $group = $groupsByName[$group->parent];
        }
        return $group->ledger_group_name;
    }

    public function stockItemJsonImport(Request $request)
    {
        try {

            $this->validateLicenseNumber($request);

            $jsonData = null;
            $fileName = 'tally_stock_item_data_' . date('YmdHis') . '.json';

            if ($request->hasFile('uploadFile')) {
                $uploadedFile = $request->file('uploadFile');
                $jsonFilePath = storage_path('app/' . $fileName);

                $uploadedFile->move(storage_path('app'), $fileName);
                $jsonData = file_get_contents($jsonFilePath);

            } else {
                $jsonData = $request->getContent();
                $jsonFilePath = storage_path('app/' . $fileName);
                file_put_contents($jsonFilePath, $jsonData);
            }

            $data = json_decode($jsonData, true);

            $result = $this->findTallyMessage($data);

            if ($result === null) {
                throw new \Exception('TALLYMESSAGE key not found in the JSON data.');
            }

            $messagesPath = $result['path'];
            $messages = $result['value'];

            $unitCount = 0;
            $godownCount = 0;
            $stockGroupCount = 0;
            $stockItemCount = 0;

            $unitIds = [];

            foreach ($messages as $message) {
                if (isset($message['UNIT'])) {
                    $unitData = $message['UNIT'];
                    // Log::info('Unit Data:', ['unitData' => $unitData]);

                    $reportingUQCDetails = $unitData['REPORTINGUQCDETAILS.LIST'] ?? [];
                    $reportingUQCName = $reportingUQCDetails['REPORTINGUQCNAME'] ?? null;
                    $applicableFrom = $reportingUQCDetails['APPLICABLEFROM'] ?? null;

                    $name = is_array($unitData['NAME']) ? $unitData['NAME'][0] : $unitData['NAME'];

                    $guid = $unitData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;

                    $tallyUnit = TallyUnit::updateOrCreate(
                        ['unit_guid' => $unitData['GUID'] ?? null],
                        [
                            'company_id' => $companyId,
                            'alter_id' => $unitData['ALTERID'] ?? null,
                            'unit_name' => $name,
                            'is_gst_excluded' => ($unitData['ISGSTEXCLUDED'] ?? null) === 'Yes' ? true : false,
                            'is_simple_unit' => ($unitData['ISSIMPLEUNIT'] ?? null) === 'Yes' ? true : false,
                            'reporting_uqc_name' => $reportingUQCName,
                            'applicable_from' => $applicableFrom,
                        ]
                    );

                    if ($tallyUnit) {
                        $unitCount++;
                    }
                    if (!$tallyUnit) {
                        throw new \Exception('Failed to create or update tally unit record.');
                    }
                    $unitIds[$name] = $tallyUnit->unit_id;
                }
            }

            foreach ($messages as $message) {
                if (isset($message['GODOWN'])) {
                    $godownData = $message['GODOWN'];
                    // Log::info('Godown Data:', ['godownData' => $godownData]);

                    $nameField = $godownData['LANGUAGENAME.LIST']['NAME.LIST']['NAME'] ?? null;
                    if (is_array($nameField)) {
                        $nameField = implode(', ', $nameField);
                    }

                    $guid = $godownData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;
                    $companyIds[$companyId] = true;

                    $tallyGodown = TallyGodown::updateOrCreate(
                        ['godown_guid' => $godownData['GUID'] ?? null],
                        [
                            'company_id' => $companyId,
                            'parent' => $godownData['PARENT'] ?? null,
                            'alter_id' => $godownData['ALTERID'] ?? null,
                            'godown_name' => $nameField,
                        ]
                    );

                    if ($tallyGodown) {
                        $godownCount++;
                    }
                    if (!$tallyGodown) {
                        throw new \Exception('Failed to create or update tally Godown record.');
                    }
                }
            }

            foreach ($messages as $message) {
                if (isset($message['STOCKGROUP'])) {
                    $stockGroupData = $message['STOCKGROUP'];
                    // Log::info('STOCKGROUP Data:', ['stockGroupData' => $stockGroupData]);

                    $nameField = $stockGroupData['LANGUAGENAME.LIST']['NAME.LIST']['NAME'] ?? null;
                    if (is_array($nameField)) {
                        $nameField = implode(', ', $nameField);
                    }

                    $guid = $stockGroupData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;

                    $tallystockGroup = TallyItemGroup::updateOrCreate(
                        ['item_group_guid' => $stockGroupData['GUID'] ?? null],
                        [
                            'company_id' => $companyId,
                            'parent' => $stockGroupData['PARENT'] ?? null,
                            'alter_id' => $stockGroupData['ALTERID'] ?? null,
                            'item_group_name' => $nameField,
                        ]
                    );

                    if ($tallystockGroup) {
                        $stockGroupCount++;
                    }
                    if (!$tallystockGroup) {
                        throw new \Exception('Failed to create or update tally Stock Group record.');
                    }
                }
            }

            foreach ($messages as $message) {
                if (isset($message['STOCKITEM'])) {
                    $stockItemData = $message['STOCKITEM'];
                    // Log::info('Stock Item Data:', ['stockItemData' => $stockItemData]);

                    $nameField = $stockItemData['LANGUAGENAME.LIST']['NAME.LIST']['NAME'] ?? [];
                    $aliases = [];
                    if (is_array($nameField)) {
                        $languageName = $nameField[0] ?? null;
                        for ($i = 1; $i < count($nameField); $i++) {
                            $aliases[] = $nameField[$i] ?? null;
                        }
                    } else {
                        $languageName = $nameField;
                    }
                    $alias1 = $aliases[0] ?? null;
                    $alias2 = $aliases[1] ?? null;
                    $alias3 = $aliases[2] ?? null;

                    $igstRate = null;
                    if (isset($stockItemData['GSTDETAILS.LIST']['STATEWISEDETAILS.LIST']['RATEDETAILS.LIST'])) {
                        foreach ($stockItemData['GSTDETAILS.LIST']['STATEWISEDETAILS.LIST']['RATEDETAILS.LIST'] as $rateDetail) {
                            if ($rateDetail['GSTRATEDUTYHEAD'] === 'IGST') {
                                $igstRate = $rateDetail['GSTRATE'] ?? null;
                                break;
                            }
                        }
                    }

                    $rateString = $stockItemData['OPENINGRATE'] ?? null;
                    $rate = $unit = null;

                    if ($rateString) {
                        $parts = explode('/', $rateString);
                        if (count($parts) === 2) {
                            $rate = trim($parts[0]);
                            $unit = trim($parts[1]);
                        } else {
                            $rate = $rateString;
                        }
                    }

                    $guid = $stockItemData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);

                    $company = TallyCompany::where('company_guid', $companyGuid)->first();

                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }

                    $companyId = $company->company_id;

                    $itemParentName = $stockItemData['PARENT'] ?? null;
                    $itemGroup = TallyItemGroup::where('item_group_name', $itemParentName)
                        ->where('company_id', $companyId)->first();
                    $itemGroupIds = $itemGroup ? $itemGroup->item_group_id : null;

                    $unitName = $stockItemData['BASEUNITS'] ?? null;
                    $unitId = TallyUnit::where('unit_name', $unitName)->where('company_id', $companyId)->first();
                    $unitIds = $unitId ? $unitId->unit_id : null;

                    $itemName = $stockItemData['NAME'] ?? null;
                    if (strlen($itemName) > 255) {
                        $itemName = substr($itemName, 0, 255);
                    }

                    $openingRate = isset($stockItemData['OPENINGRATE'])
                        ? (is_numeric($cleaned = preg_replace('/[^-0-9.]/', '', $stockItemData['OPENINGRATE']))
                            ? (float)$cleaned
                            : 0)
                        : 0;

                    $openingValue = isset($stockItemData['OPENINGVALUE'])
                        ? (is_numeric($cleaned = preg_replace('/[^-0-9.]/', '', $stockItemData['OPENINGVALUE']))
                            ? (float)$cleaned
                            : 0)
                        : 0;
                    $openingBalance = isset($stockItemData['OPENINGBALANCE'])
                        ? (is_numeric($cleaned = preg_replace('/[^-0-9.]/', '', $stockItemData['OPENINGBALANCE']))
                            ? (float)$cleaned
                            : 0)
                        : 0;

                    // if opening value is negative then opening balance will be negative make sure
                    // opening balance is negative
                    if ($openingValue < 0 && $openingBalance > 0) {
                        $openingBalance = -$openingBalance;
                    }

                    $tallyStockItem = TallyItem::updateOrCreate(
                        ['item_guid' => $stockItemData['GUID'] ?? null],
                        [
                            'company_id' => $companyId,
                            'item_group_id' => $itemGroupIds,
                            'unit_id' => $unitIds,
                            'item_name' => $itemName,
                            'parent' => $stockItemData['PARENT'] ?? null,
                            'category' => $stockItemData['CATEGORY'] ?? null,
                            'gst_applicable' => isset($stockItemData['GSTAPPLICABLE']) && $stockItemData['GSTAPPLICABLE'] === 'Yes',
                            'vat_applicable' => isset($stockItemData['VATAPPLICABLE']) && $stockItemData['VATAPPLICABLE'] === 'Yes',
                            'tax_classification_name' => $stockItemData['TAXCLASSIFICATIONNAME'] ?? null,
                            'gst_type_of_supply' => $stockItemData['GSTTYPEOFSUPPLY'] ?? null,
                            'excise_applicability' => $stockItemData['EXCISEAPPLICABILITY'] ?? null,
                            'sales_tax_cess_applicable' => $stockItemData['SALESTAXCESSAPPLICABLE'] ?? null,
                            'costing_method' => $stockItemData['COSTINGMETHOD'] ?? null,
                            'valuation_method' => $stockItemData['VALUATIONMETHOD'] ?? null,
                            'additional_units' => $stockItemData['ADDITIONALUNITS'] ?? null,
                            'excise_item_classification' => $stockItemData['EXCISEITEMCLASSIFICATION'] ?? null,
                            'vat_base_unit' => $stockItemData['VATBASEUNIT'] ?? null,
                            'is_cost_centres_on' => isset($stockItemData['ISCOSTCENTRESON']) && $stockItemData['ISCOSTCENTRESON'] === 'Yes',
                            'is_batch_wise_on' => isset($stockItemData['ISBATCHWISEON']) && $stockItemData['ISBATCHWISEON'] === 'Yes',
                            'is_perishable_on' => isset($stockItemData['ISPERISHABLEON']) && $stockItemData['ISPERISHABLEON'] === 'Yes',
                            'is_entry_tax_applicable' => isset($stockItemData['ISENTRYTAXAPPLICABLE']) && $stockItemData['ISENTRYTAXAPPLICABLE'] === 'Yes',
                            'is_cost_tracking_on' => isset($stockItemData['ISCOSTTRACKINGON']) && $stockItemData['ISCOSTTRACKINGON'] === 'Yes',
                            'is_updating_target_id' => isset($stockItemData['ISUPDATINGTARGETID']) && $stockItemData['ISUPDATINGTARGETID'] === 'Yes',
                            'is_security_on_when_entered' => isset($stockItemData['ISSECURITYONWHENENTERED']) && $stockItemData['ISSECURITYONWHENENTERED'] === 'Yes',
                            'as_original' => isset($stockItemData['ASORIGINAL']) && $stockItemData['ASORIGINAL'] === 'Yes',
                            'is_rate_inclusive_vat' => isset($stockItemData['ISRATEINCLUSIVEVAT']) && $stockItemData['ISRATEINCLUSIVEVAT'] === 'Yes',
                            'ignore_physical_difference' => isset($stockItemData['IGNOREPHYSICALDIFFERENCE']) && $stockItemData['IGNOREPHYSICALDIFFERENCE'] === 'Yes',
                            'ignore_negative_stock' => isset($stockItemData['IGNORENEGATIVESTOCK']) && $stockItemData['IGNORENEGATIVESTOCK'] === 'Yes',
                            'treat_sales_as_manufactured' => isset($stockItemData['TREATSALESASMANUFACTURED']) && $stockItemData['TREATSALESASMANUFACTURED'] === 'Yes',
                            'treat_purchases_as_consumed' => isset($stockItemData['TREATPURCHASESASCONSUMED']) && $stockItemData['TREATPURCHASESASCONSUMED'] === 'Yes',
                            'treat_rejects_as_scrap' => isset($stockItemData['TREATREJECTSASSCRAP']) && $stockItemData['TREATREJECTSASSCRAP'] === 'Yes',
                            'has_mfg_date' => isset($stockItemData['HASMFGDATE']) && $stockItemData['HASMFGDATE'] === 'Yes',
                            'allow_use_of_expired_items' => isset($stockItemData['ALLOWUSEOFEXPIREDITEMS']) && $stockItemData['ALLOWUSEOFEXPIREDITEMS'] === 'Yes',
                            'ignore_batches' => isset($stockItemData['IGNOREBATCHES']) && $stockItemData['IGNOREBATCHES'] === 'Yes',
                            'ignore_godowns' => isset($stockItemData['IGNOREGODOWNS']) && $stockItemData['IGNOREGODOWNS'] === 'Yes',
                            'adj_diff_in_first_sale_ledger' => isset($stockItemData['ADJDIFFINFIRSTSALELEDGER']) && $stockItemData['ADJDIFFINFIRSTSALELEDGER'] === 'Yes',
                            'adj_diff_in_first_purc_ledger' => isset($stockItemData['ADJDIFFINFIRSTPURCLEDGER']) && $stockItemData['ADJDIFFINFIRSTPURCLEDGER'] === 'Yes',
                            'cal_con_mrp' => isset($stockItemData['CALCONMRP']) && $stockItemData['CALCONMRP'] === 'Yes',
                            'exclude_jrnl_for_valuation' => isset($stockItemData['EXCLUDEJRNLFORVALUATION']) && $stockItemData['EXCLUDEJRNLFORVALUATION'] === 'Yes',
                            'is_mrp_incl_of_tax' => isset($stockItemData['ISMRPINCLOFTAX']) && $stockItemData['ISMRPINCLOFTAX'] === 'Yes',
                            'is_addl_tax_exempt' => isset($stockItemData['ISADDLTAXEXEMPT']) && $stockItemData['ISADDLTAXEXEMPT'] === 'Yes',
                            'is_supplementry_duty_on' => isset($stockItemData['ISSUPPLEMENTRYDUTYON']) && $stockItemData['ISSUPPLEMENTRYDUTYON'] === 'Yes',
                            'gvat_is_excise_appl' => isset($stockItemData['GVATISEXCISEAPPL']) && $stockItemData['GVATISEXCISEAPPL'] === 'Yes',
                            'is_additional_tax' => isset($stockItemData['ISADDITIONALTAX']) && $stockItemData['ISADDITIONALTAX'] === 'Yes',
                            'is_cess_exempted' => isset($stockItemData['ISCESSEXEMPTED']) && $stockItemData['ISCESSEXEMPTED'] === 'Yes',
                            'reorder_as_higher' => isset($stockItemData['REORDERASHIGHER']) && $stockItemData['REORDERASHIGHER'] === 'Yes',
                            'min_order_as_higher' => isset($stockItemData['MINORDERASHIGHER']) && $stockItemData['MINORDERASHIGHER'] === 'Yes',
                            'is_excise_calculate_on_mrp' => isset($stockItemData['ISEXCISECALCULATEONMRP']) && $stockItemData['ISEXCISECALCULATEONMRP'] === 'Yes',
                            'inclusive_tax' => isset($stockItemData['INCLUSIVETAX']) && $stockItemData['INCLUSIVETAX'] === 'Yes',
                            'gst_calc_slab_on_mrp' => isset($stockItemData['GSTCALCSLABONMRP']) && $stockItemData['GSTCALCSLABONMRP'] === 'Yes',
                            'modify_mrp_rate' => isset($stockItemData['MODIFYMRPRATE']) && $stockItemData['MODIFYMRPRATE'] === 'Yes',
                            'alter_id' => $stockItemData['ALTERID'] ?? null,
                            'denominator' => $stockItemData['DENOMINATOR'] ?? null,
                            'basic_rate_of_excise' => $stockItemData['BASICRATEOFEXCISE'] ?? null,
                            'base_units' => $stockItemData['BASEUNITS'] ?? null,
                            'opening_balance' => $openingBalance,
                            'opening_value' => $openingValue,
                            'opening_rate' => $openingRate,
                            // 'unit' => $unit,
                            'igst_rate' => $igstRate,
                            'hsn_code' => $stockItemData['HSNDETAILS.LIST']['HSNCODE'] ?? null,
                            'gst_details' => json_encode($stockItemData['GSTDETAILS.LIST'] ?? []),
                            'hsn_details' => json_encode($stockItemData['HSNDETAILS.LIST'] ?? []),
                            'alias1' => $alias1,
                            'alias2' => $alias2,
                            'alias3' => $alias3,
                            'batch_allocations' => json_encode($stockItemData['BATCHALLOCATIONS.LIST'] ?? []),
                        ]
                    );

                    if ($tallyStockItem) {
                        $stockItemCount++;
                    }
                    if (!$tallyStockItem) {
                        throw new \Exception('Failed to create or update tally stock item record.');
                    }
                }
            }

            return response()->json([
                'message' => 'Stock item saved',
                'units_inserted' => $unitCount,
                'godowns_inserted' => $godownCount,
                'stock_groups_inserted' => $stockGroupCount,
                'stock_items_inserted' => $stockItemCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error importing stock items:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function voucherTypeJsonImport(Request $request)
    {
        try {
            $this->validateLicenseNumber($request);

            $jsonData = null;
            $fileName = 'tally_voucher_type_data_' . date('YmdHis') . '.json';

            if ($request->hasFile('uploadFile')) {
                $uploadedFile = $request->file('uploadFile');
                $jsonFilePath = storage_path('app/' . $fileName);
                $uploadedFile->move(storage_path('app'), $fileName);
                $jsonData = file_get_contents($jsonFilePath);
            } else {
                $jsonData = $request->getContent();
                $jsonFilePath = storage_path('app/' . $fileName);
                file_put_contents($jsonFilePath, $jsonData);
            }

            $data = json_decode($jsonData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
            }

            if (!isset($data['BODY']['DATA']['COLLECTION']['VOUCHERTYPE'])) {
                throw new \Exception('VOUCHERTYPE data not found in JSON structure.');
            }

            $insertedRecordsCount = 0;
            foreach ($data['BODY']['DATA']['COLLECTION']['VOUCHERTYPE'] as $voucherTypeData) {

                // Access GUID as a string
                $guid = $voucherTypeData['GUID'][''] ?? null;
                if ($guid) {
                    $companyGuid = substr($guid, 0, 36);
                    $company = TallyCompany::where('company_guid', $companyGuid)->first();
                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }
                    $companyId = $company->company_id;

                    $tallyVoucherType = TallyVoucherType::updateOrCreate(
                        [
                            'voucher_type_guid' => $guid,
                        ],
                        [
                            'company_id' => $companyId,
                            'alter_id' => $voucherTypeData['ALTERID'][''] ?? null,
                            'voucher_type_name' => $voucherTypeData['NAME'] ?? null,
                            'parent' => $voucherTypeData['PARENT'][''] ?? null,
                            'numbering_method' => $voucherTypeData['NUMBERINGMETHOD'][''] ?? null,
                            'prevent_duplicate' => isset($voucherTypeData['PREVENTDUPLICATES']) && $voucherTypeData['PREVENTDUPLICATES'][''] === 'Yes',
                            'use_zero_entries' => isset($voucherTypeData['USEZEROENTRIES']) && $voucherTypeData['USEZEROENTRIES'][''] === 'Yes',
                            'is_deemed_positive' => isset($voucherTypeData['ISDEEMEDPOSITIVE']) && $voucherTypeData['ISDEEMEDPOSITIVE'][''] === 'Yes',
                            'affects_stock' => isset($voucherTypeData['AFFECTSSTOCK']) && $voucherTypeData['AFFECTSSTOCK'][''] === 'Yes',
                            'is_active' => isset($voucherTypeData['ISACTIVE']) && $voucherTypeData['ISACTIVE'][''] === 'Yes',
                        ]
                    );
                    $insertedRecordsCount++;
                }
            }

            return response()->json([
                'message' => 'Tally voucher type data processed successfully.',
                'records_inserted' => $insertedRecordsCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error in voucherTypeJsonImport function', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function voucherJsonImport(Request $request)
    {
        try {

            $this->validateLicenseNumber($request);

            $jsonData = null;
            $fileName = 'tally_voucher_data_' . date('YmdHis') . '.json';

            if ($request->hasFile('uploadFile')) {
                $uploadedFile = $request->file('uploadFile');
                $jsonFilePath = storage_path('app/' . $fileName);

                $uploadedFile->move(storage_path('app'), $fileName);
                $jsonData = file_get_contents($jsonFilePath);

            } else {
                $jsonData = $request->getContent();
                $jsonFilePath = storage_path('app/' . $fileName);
                file_put_contents($jsonFilePath, $jsonData);
            }

            $data = json_decode($jsonData, true);

            $result = $this->findTallyMessage($data);

            if ($result === null) {
                throw new \Exception('TALLYMESSAGE key not found in the JSON data.');
            }

            $messagesPath = $result['path'];
            $messages = $result['value'];

            $voucherCount = 0;

            foreach ($messages as $message) {
                if (isset($message['VOUCHER'])) {
                    $voucherData = $message['VOUCHER'];
                    // Log::info('VOUCHER Data:', ['voucherData' => $voucherData]);

                    $guid = $voucherData['GUID'] ?? null;
                    $companyGuid = substr($guid, 0, 36);
                    $company = TallyCompany::where('company_guid', $companyGuid)->first();
                    if (!$company) {
                        Log::error('Company GUID not found in tally_companies: ' . $companyGuid);
                        continue;
                    }
                    $companyId = $company->company_id;

                    $consigneeAddressList = $voucherData['BASICBUYERADDRESS.LIST']['BASICBUYERADDRESS'] ?? null;
                    if (is_array($consigneeAddressList)) {
                        $consigneeAddressList = implode(', ', $consigneeAddressList);
                    }

                    $buyerAddressList = $voucherData['ADDRESS.LIST']['ADDRESS'] ?? null;
                    if (is_array($buyerAddressList)) {
                        $buyerAddressList = implode(', ', $buyerAddressList);
                    }

                    $invoiceDelNotes = is_array($voucherData['INVOICEDELNOTES.LIST'] ?? null) ? $voucherData['INVOICEDELNOTES.LIST'] : [];
                    $deliveryNotes = [];
                    $formattedShippingDates = [];
                    foreach ($invoiceDelNotes as $note) {
                        if (isset($note['BASICSHIPDELIVERYNOTE']) && isset($note['BASICSHIPPINGDATE'])) {
                            $formattedDate = $this->convertToDesiredDateFormat($note['BASICSHIPPINGDATE']);
                            $formattedShippingDates[] = $formattedDate;
                            $deliveryNotes[] = $note['BASICSHIPDELIVERYNOTE'];
                        }
                    }
                    $deliveryNotesStr = implode(', ', $deliveryNotes);


                    $ledgerEntries = $this->normalizeEntries($this->ensureArray($voucherData['LEDGERENTRIES.LIST'] ?? []));
                    $allLedgerEntries = $this->normalizeEntries($this->ensureArray($voucherData['ALLLEDGERENTRIES.LIST'] ?? []));
                    $combinedLedgerEntries = array_merge($ledgerEntries, $allLedgerEntries);


                    $inventoryEntries = $this->normalizeEntries($this->ensureArray($voucherData['ALLINVENTORYENTRIES.LIST'] ?? []));
                    $accountingAllocations = [];
                    foreach ($inventoryEntries as $inventoryEntry) {
                        if (isset($inventoryEntry['ACCOUNTINGALLOCATIONS.LIST'])) {
                            $accountingAllocations = array_merge($accountingAllocations, $this->normalizeEntries($inventoryEntry['ACCOUNTINGALLOCATIONS.LIST']));
                        }
                    }
                    $accountingAllocations = $this->processAccountingAllocations($accountingAllocations, $companyId);


                    $batchAllocations = [];
                    foreach ($inventoryEntries as $inventoryEntry) {
                        if (isset($inventoryEntry['BATCHALLOCATIONS.LIST'])) {
                            $batchAllocations[$inventoryEntry['STOCKITEMNAME']] = $this->normalizeEntries($inventoryEntry['BATCHALLOCATIONS.LIST']);
                        }
                    }


                    $billAllocations = [];
                    foreach ($combinedLedgerEntries as $ledgerEntry) {
                        if (isset($ledgerEntry['BILLALLOCATIONS.LIST'])) {
                            $billAllocations[$ledgerEntry['LEDGERNAME']] = $this->normalizeEntries($ledgerEntry['BILLALLOCATIONS.LIST']);
                        }
                    }

                    $bankAllocations = [];
                    foreach ($combinedLedgerEntries as $ledgerEntry) {
                        if (isset($ledgerEntry['BANKALLOCATIONS.LIST'])) {
                            $bankAllocations[$ledgerEntry['LEDGERNAME']] = $this->normalizeEntries($ledgerEntry['BANKALLOCATIONS.LIST']);
                        }
                    }

                    $voucherType = $voucherData['VOUCHERTYPENAME'] ?? null;
                    $voucherTypeId = TallyVoucherType::where('voucher_type_name', $voucherType)
                        ->where('company_Id', $companyId)
                        ->value('voucher_type_id');

                    // if vouchertypeid is null print query
                    if (!$voucherTypeId) {
                        Log::error('Voucher Type ID not found for voucher type: ' . $voucherType . ' and company ID: ' . $companyId);
                    }


                    // Log::info('jsonFilePath Data:', ['jsonFilePath' => $jsonFilePath]);

                    $tallyVoucher = TallyVoucher::updateOrCreate(
                        [
                            'voucher_guid' => $voucherData['GUID'],
                            'company_id' => $companyId
                        ],

                        [
                            'voucher_type_id' => $voucherTypeId,
                            // 'voucher_type' => $voucherData['VOUCHERTYPENAME'] ?? null,
                            'is_cancelled' => isset($voucherData['ISCANCELLED']) && $voucherData['ISCANCELLED'] === 'Yes',
                            'is_optional' => isset($voucherData['ISOPTIONAL']) && $voucherData['ISOPTIONAL'] === 'Yes',
                            'alter_id' => $voucherData['ALTERID'] ?? null,
                            'voucher_number' => $voucherData['VOUCHERNUMBER'] ?? null,
                            'voucher_date' => $voucherData['DATE'] ?? null,
                            'reference_date' => !empty($voucherData['REFERENCEDATE']) ? $voucherData['REFERENCEDATE'] : null,
                            'reference_no' => $voucherData['REFERENCE'] ?? null,
                            'place_of_supply' => $voucherData['PLACEOFSUPPLY'] ?? null,
                            'country_of_residense' => $voucherData['COUNTRYOFRESIDENCE'] ?? null,
                            'gst_registration_type' => $voucherData['GSTREGISTRATIONTYPE'] ?? null,
                            'numbering_style' => $voucherData['NUMBERINGSTYLE'] ?? null,
                            'narration' => $voucherData['NARRATION'] ?? null,
                            'order_no' => $voucherData['INVOICEORDERLIST.LIST']['BASICPURCHASEORDERNO'] ?? null,
                            'order_date' => $voucherData['INVOICEORDERLIST.LIST']['BASICORDERDATE'] ?? null,
                            'ship_doc_no' => $voucherData['BASICSHIPDOCUMENTNO'] ?? null,
                            'ship_by' => $voucherData['BASICSHIPPEDBY'] ?? null,
                            'final_destination' => $voucherData['BASICFINALDESTINATION'] ?? null,
                            'bill_lading_no' => $voucherData['BILLOFLADINGNO'] ?? null,
                            'bill_lading_date' => !empty($voucherData['BILLOFLADINGDATE']) ? $voucherData['BILLOFLADINGDATE'] : null,
                            'vehicle_no' => $voucherData['BASICSHIPVESSELNO'] ?? null,
                            'terms' => is_array($voucherData['BASICORDERTERMS.LIST']['BASICORDERTERMS'] ?? null)
                                ? implode(', ', $voucherData['BASICORDERTERMS.LIST']['BASICORDERTERMS'])
                                : ($voucherData['BASICORDERTERMS.LIST']['BASICORDERTERMS'] ?? null),
                            'consignee_name' => $voucherData['BASICBUYERNAME'] ?? null,
                            'consignee_state_name' => $voucherData['CONSIGNEESTATENAME'] ?? null,
                            'consignee_gstin' => $voucherData['CONSIGNEEGSTIN'] ?? null,
                            'consignee_addr' => $consigneeAddressList,
                            'buyer_name' => $voucherData['BASICBUYERNAME'] ?? null,
                            'buyer_addr' => $buyerAddressList,
                            'delivery_notes' => $deliveryNotesStr,
                            'delivery_dates' => json_encode($formattedShippingDates),
                            'due_date_payment' => $voucherData['BASICDUEDATEOFPYMT'] ?? null,
                            'buyer_gstin' => $voucherData['PARTYGSTIN'] ?? null,
                            'order_ref' => $voucherData['BASICORDERREF'] ?? null,
                            'cost_center_name' => $voucherData['COSTCENTRENAME'] ?? null,
                            'cost_center_amount' => $voucherData['COSTCENTREAMOUNT'] ?? null,
                            'json_path' => $jsonFilePath,
                        ]
                    );

                    if ($tallyVoucher) {
                        $voucherCount++;
                    }

                    if (!$tallyVoucher) {
                        throw new \Exception('Failed to create or update tally Voucher record.');
                    }

                    $voucherHeadIds = $this->processLedgerEntries($voucherData, $tallyVoucher, $companyId);

                    $inventoryEntriesWithId = $this->processInventoryEntries($voucherData['ALLINVENTORYENTRIES.LIST'] ?? [], $voucherHeadIds, $companyId);

                    $this->processAccountingAllocationForVoucher($tallyVoucher->voucher_id, $accountingAllocations, $companyId);

                    if (!empty($inventoryEntriesWithId)) {
                        $this->processBatchAllocationsForVoucher($inventoryEntriesWithId, $batchAllocations, $companyId);
                    } else {
                        // Log::info('No inventory entries with ID found; skipping batch allocations.');
                    }

                    $this->processBillAllocationsForVoucher($voucherHeadIds, $billAllocations);
                    $this->processBankAllocationsForVoucher($voucherHeadIds, $bankAllocations);


                }
            }

            return response()->json([
                'message' => 'Vouchers saved',
                'vouchers_processed' => $voucherCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Tally voucher data:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'Failed to save Tally data', 'message' => $e->getMessage()], 500);
        }
    }

    private function processLedgerEntries(array $voucherData, TallyVoucher $tallyVoucher, $companyId)
    {
        $ledgerEntries = array_merge(
            $this->normalizeEntries($voucherData['LEDGERENTRIES.LIST'] ?? []),
            $this->normalizeEntries($voucherData['ALLLEDGERENTRIES.LIST'] ?? [])
        );

        $voucherHeadIds = [];

        foreach ($ledgerEntries as $ledgerEntry) {
            $ledgerName = htmlspecialchars_decode($ledgerEntry['LEDGERNAME'] ?? '');
            $amount = $ledgerEntry['AMOUNT'] ?? 0;
            $entryType = $amount < 0 ? "debit" : "credit";

            $ledgerId = TallyLedger::where('ledger_name', $ledgerName)
                ->where('company_id', $companyId)
                ->value('ledger_id');


            if (!$ledgerId) {
                Log::error('Ledger not found', [
                    'ledger_name' => $ledgerName,
                    'company_id' => $companyId,
                    'voucher_id' => $tallyVoucher->voucher_id ?? null,
                    'ledger_entry' => $ledgerEntry
                ]);
                // Optionally, you can throw an exception or create the ledger here
                // throw new \Exception("Ledger not found: $ledgerName for company_id: $companyId");
            }

            $ledgerHeadData = [
                'voucher_id' => $tallyVoucher->voucher_id,
                'amount' => $amount,
                'entry_type' => $entryType,
                'ledger_id' => $ledgerId,
                'is_party_ledger' => ($ledgerEntry['ISPARTYLEDGER'] ?? null) === 'Yes' ? true : false,
                'is_deemed_positive' => isset($ledgerEntry['ISDEEMEDPOSITIVE']) && $ledgerEntry['ISDEEMEDPOSITIVE'] === 'Yes',
            ];

            try {
                $voucherHead = TallyVoucherHead::updateOrCreate($ledgerHeadData);

                if ($voucherHead) {
                    $voucherHeadIds[] = $voucherHead->voucher_head_id;
                } else {
                    Log::error('Error saving Tally voucher data:', ['data' => $ledgerHeadData]);
                }
            } catch (\Exception $e) {
                Log::error('Exception while saving Tally voucher data:', [
                    'exception' => $e->getMessage(),
                    'data' => $ledgerHeadData
                ]);
            }
        }
        return $voucherHeadIds;
    }

    private function processAccountingAllocations(array $entries, $companyId)
    {
        $ledgerEntries = [];

        foreach ($entries as $entry) {
            if (isset($entry['LEDGERNAME'], $entry['AMOUNT'])) {
                $ledgerName = htmlspecialchars_decode($entry['LEDGERNAME']);
                $amount = $entry['AMOUNT'];
                $entryType = $amount < 0 ? "debit" : "credit";

                $ledgerId = TallyLedger::where('ledger_name', $ledgerName)
                    ->where('company_Id', $companyId)
                    ->value('ledger_id');


                if (!$ledgerId) {
                    Log::error('Ledger GUID not found in database for ledger: ' . $ledgerName);
                    continue;
                }

                if (isset($ledgerEntries[$ledgerName])) {
                    $ledgerEntries[$ledgerName]['amount'] += $amount;
                } else {
                    $ledgerEntries[$ledgerName] = [
                        'amount' => $amount,
                        'entry_type' => $entryType,
                        'ledger_id' => $ledgerId,
                        'is_deemed_positive' => isset($entry['ISDEEMEDPOSITIVE']) && $entry['ISDEEMEDPOSITIVE'] === 'Yes',
                    ];
                }
            } else {
                Log::error('Missing or invalid LEDGERNAME or AMOUNT in LEDGERENTRIES.LIST entry: ' . json_encode($entry));
            }
        }
        return array_values($ledgerEntries);
    }

    private function processAccountingAllocationForVoucher($voucherId, array $entries)
    {
        $voucherHeadIds = [];

        foreach ($entries as $entry) {
            $voucherHead = TallyVoucherHead::updateOrCreate([
                'voucher_id' => $voucherId,
                'amount' => $entry['amount'],
                'entry_type' => $entry['entry_type'],
                'ledger_id' => $entry['ledger_id'],
                'is_deemed_positive' => isset($entry['ISDEEMEDPOSITIVE']) && $entry['ISDEEMEDPOSITIVE'] === 'Yes',

            ]);

            $voucherHeadIds[] = [
                'voucher_head_id' => $voucherHead->voucher_head_id,
            ];
        }

        return $voucherHeadIds;
    }

    private function processInventoryEntries($inventoryEntries, array $voucherHeadIds, $companyId)
    {
        // Log::info('Processing Inventory Entries:', ['count' => is_array($inventoryEntries) ? count($inventoryEntries) : 1]);
        // Log::info('Available Voucher Head IDs:', ['voucherHeadIds' => $voucherHeadIds]);

        if (empty($voucherHeadIds)) {
            Log::error('No voucher_head_id available for inventory entry.');
            return;
        }

        $voucherHeadId = $voucherHeadIds[0];
        // Log::info('Using Voucher Head ID:', ['voucherHeadId' => $voucherHeadId]);

        $inventoryEntries = $this->normalizeEntries($inventoryEntries);
        // Log::info('Normalized Inventory Entries:', ['entries' => $inventoryEntries]);

        $inventoryEntriesWithId = [];

        foreach ($inventoryEntries as $inventoryEntry) {
            $itemName = trim(htmlspecialchars_decode($inventoryEntry['STOCKITEMNAME'] ?? ''));

            if (!$itemName) {
                Log::warning('Skipped inventory entry due to missing STOCKITEMNAME:', ['entry' => $inventoryEntry]);
                continue;
            }

            $itemId = TallyItem::whereRaw('LOWER(item_name) = ?', [strtolower($itemName)])->where('company_id', $companyId)->value('item_id');

            if (!$itemId) {
                Log::error('Item ID not found for stock item:', [
                    'stock_item_name' => $itemName,
                    'attempted_match' => strtolower($itemName),
                    'inventoryEntry' => $inventoryEntry
                ]);
                continue;
            }

            $rateString = $inventoryEntry['RATE'] ?? null;
            $rate = null;
            $unit = null;
            if ($rateString) {
                $parts = explode('/', $rateString);
                if (count($parts) === 2) {
                    $rate = trim($parts[0]);
                    $unit = trim($parts[1]);
                } else {
                    Log::warning('Rate format not as expected:', ['stock_item_name' => $itemName, 'rate' => $rateString]);
                    $rate = $rateString;
                }
            }

            $unitId = null;
            if ($unit) {
                $unitId = TallyUnit::whereRaw('LOWER(unit_name) = ?', [strtolower($unit)])->where('company_id', $companyId)->value('unit_id');
                // Log::info('Extracted unit and unitId Data:', ['unit' => $unit, 'unitId' => $unitId]);
            }

            $billed_qty = $this->extractNumericValue($inventoryEntry['BILLEDQTY'] ?? null);
            $actual_qty = $this->extractNumericValue($inventoryEntry['ACTUALQTY'] ?? null);

            $igstRate = null;
            if (isset($inventoryEntry['RATEDETAILS.LIST'])) {
                foreach ($this->ensureArray($inventoryEntry['RATEDETAILS.LIST']) as $rateDetail) {
                    if (isset($rateDetail['GSTRATEDUTYHEAD']) && $rateDetail['GSTRATEDUTYHEAD'] === 'IGST') {
                        $igstRate = $rateDetail['GSTRATE'] ?? null;
                        break;
                    }
                }
            }

            $inventoryData = [
                'voucher_head_id' => $voucherHeadId,
                'item_id' => $itemId,  // Ensure item_id is included in the data array
                'unit_id' => $unitId,
                'gst_taxability' => $inventoryEntry['GSTOVRDNTAXABILITY'] ?? null,
                'gst_source_type' => $inventoryEntry['GSTSOURCETYPE'] ?? null,
                'gst_item_source' => $inventoryEntry['GSTITEMSOURCE'] ?? null,
                'gst_ledger_source' => $inventoryEntry['GSTLEDGERSOURCE'] ?? null,
                'hsn_source_type' => $inventoryEntry['HSNSOURCETYPE'] ?? null,
                'hsn_item_source' => $inventoryEntry['HSNITEMSOURCE'] ?? null,
                'gst_rate_infer_applicability' => $inventoryEntry['GSTRATEINFERAPPLICABILITY'] ?? null,
                'gst_hsn_infer_applicability' => $inventoryEntry['GSTHSNINFERAPPLICABILITY'] ?? null,
                'rate' => $rate,
                'billed_qty' => $billed_qty ?? 0,
                'actual_qty' => $actual_qty ?? 0,
                'amount' => $inventoryEntry['AMOUNT'] ?? 0,
                'discount' => $inventoryEntry['DISCOUNT'] ?? 0,
                'igst_rate' => $igstRate,
                'gst_hsn_name' => $inventoryEntry['GSTHSNNAME'] ?? null,
            ];

            try {
                $tallyVoucherItem = TallyVoucherItem::create($inventoryData);

                $inventoryEntriesWithId[] = [
                    'voucher_item_id' => $tallyVoucherItem->voucher_item_id,
                    'stock_item_name' => $itemName,
                ];

                // Log::info('Inventory Entry Processed:', ['inventoryData' => $inventoryData]);

            } catch (\Exception $e) {
                Log::error('Error saving inventory entry:', [
                    'stock_item_name' => $itemName,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $inventoryEntriesWithId;
    }

    private function processBatchAllocationsForVoucher(array $inventoryEntriesWithId, array $batchAllocations, $companyId)
    {
        $count = 0;

        foreach ($inventoryEntriesWithId as $inventoryEntries) {
            $stockItemName = $inventoryEntries['stock_item_name'];
            if (isset($batchAllocations[$stockItemName]) && is_array($batchAllocations[$stockItemName])) {
                foreach ($batchAllocations[$stockItemName] as $batch) {
                    if (isset($batch['BATCHNAME'], $batch['AMOUNT'])) {

                        $godownId = TallyGodown::select('tally_godowns.godown_id')
                            ->where('tally_godowns.godown_name', $batch['GODOWNNAME'] ?? null)
                            ->where('tally_godowns.company_id', $companyId)
                            ->value('tally_godowns.godown_id');


                        $billed_qty = $this->extractNumericValue($inventoryEntry['BILLEDQTY'] ?? null);
                        $actual_qty = $this->extractNumericValue($inventoryEntry['ACTUALQTY'] ?? null);
                        $amount = $this->extractNumericValue($inventoryEntry['AMOUNT'] ?? null);

                        TallyBatchAllocation::updateOrCreate(
                            [
                                'voucher_item_id' => $inventoryEntries['voucher_item_id'],
                                'batch_name' => $batch['BATCHNAME'],
                                'destination_godown_name' => $batch['DESTINATIONGODOWNNAME'] ?? null,
                                'amount' => $amount,
                                'actual_qty' => $actual_qty,
                                'billed_qty' => $billed_qty,
                                'order_no' => $batch['ORDERNO'] ?? null,
                                'godown_id' => $godownId,
                            ]
                        );
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    private function processBillAllocationsForVoucher($voucherHeadIds, array $billAllocations)
    {
        // Log::info('Processing Bill Allocations', ['voucherHeadIds' => $voucherHeadIds, 'billAllocations' => $billAllocations]);

        foreach ($voucherHeadIds as $voucherHead) {
            // Log::info('Current voucher head being processed:', ['voucherHead' => $voucherHead]);

            foreach ($billAllocations as $ledgerName => $bills) {
                if (is_array($bills)) {
                    foreach ($bills as $bill) {
                        // Log::info('Current bill being processed:', ['bill' => $bill]);

                        if (is_array($bill)) {
                            try {
                                if (isset($bill['NAME'], $bill['AMOUNT'])) {
                                    TallyBillAllocation::updateOrCreate(
                                        [
                                            'voucher_head_id' => $voucherHead ?? null,  // Log voucher_head_id before using
                                            'name' => $bill['NAME'],
                                        ],
                                        [
                                            'bill_amount' => $bill['AMOUNT'],
                                            'year_end' => $bill['YEAREND'] ?? null,
                                            'bill_type' => $bill['BILLTYPE'] ?? null,
                                        ]
                                    );
                                    // Log::info('Successfully processed bill allocation', ['ledger_name' => $ledgerName, 'bill' => $bill]);
                                } else {
                                    Log::error('Missing NAME or AMOUNT in BILLALLOCATIONS.LIST entry: ' . json_encode($bill));
                                }
                            } catch (\Exception $e) {
                                Log::error('Error processing bill allocation: ' . $e->getMessage());
                            }
                        } else {
                            Log::error('Invalid bill format. Expected array but got ' . gettype($bill) . ': ' . json_encode($bill));
                        }
                    }
                } else {
                    Log::error('Invalid bill allocations format for ledger name: ' . $ledgerName . '. Expected array but got ' . gettype($bills));
                }
            }
        }
    }

    private function processBankAllocationsForVoucher($voucherHeadIds, array $bankAllocations)
    {
        // Log::info('Processing Bank Allocations', ['voucherHeadIds' => $voucherHeadIds, 'bankAllocations' => $bankAllocations]);

        foreach ($voucherHeadIds as $voucherHead) {
            // Log::info('Current voucher head being processed:', ['voucherHead' => $voucherHead]);

            $ledgerName = $voucherHead['ledger_name'] ?? null;
            // Log::info("Ledger name: " . $ledgerName);

            if (isset($bankAllocations[$ledgerName]) && is_array($bankAllocations[$ledgerName])) {
                foreach ($bankAllocations[$ledgerName] as $bank) {
                    // Log::info("Processing bank allocation: " . json_encode($bank));

                    try {
                        $allocation = TallyBankAllocation::updateOrCreate(
                            [
                                'voucher_head_id' => $voucherHead ?? null,
                            ],
                            [
                                'bank_date' => $this->sanitizeDate($bank['DATE'] ?? null),
                                'instrument_date' => $this->sanitizeDate($bank['INSTRUMENTDATE'] ?? null),
                                'instrument_number' => $bank['INSTRUMENTNUMBER'] ?? null,
                                'transaction_type' => $bank['TRANSACTIONTYPE'] ?? null,
                                'bank_name' => $bank['BANKNAME'] ?? null,
                                'amount' => $bank['AMOUNT'] ?? null,
                            ]
                        );
                        // Log::info("Bank allocation stored: " . json_encode($allocation));
                    } catch (\Exception $e) {
                        Log::error("Failed to process bank allocation: " . $e->getMessage());
                    }
                }
            } else {
                // Log::error("No bank allocations found for ledger: " . $ledgerName);
            }
        }
    }

}
