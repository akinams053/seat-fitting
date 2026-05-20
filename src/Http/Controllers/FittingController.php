<?php

namespace CryptaTech\Seat\Fitting\Http\Controllers;

use CryptaTech\Seat\Fitting\Events\DoctrineUpdated;
use CryptaTech\Seat\Fitting\Events\FittingUpdated;
use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingSkillRequirement;
use CryptaTech\Seat\Fitting\Services\CorporationSkillReportService;
use CryptaTech\Seat\Fitting\Services\PersonalSkillCheckService;
use CryptaTech\Seat\Fitting\Services\SkillRequirementSyncService;
use CryptaTech\Seat\Fitting\Validation\DoctrineValidation;
use CryptaTech\Seat\Fitting\Validation\FittingValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Seat\Eveapi\Models\Alliances\Alliance;
use Seat\Eveapi\Models\Character\CharacterAffiliation;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Web\Http\Controllers\Controller;

class FittingController extends Controller
{
    public function __construct(
        private PersonalSkillCheckService $personalSkillCheck,
        private SkillRequirementSyncService $skillRequirementSync,
        private CorporationSkillReportService $corporationSkillReport,
    ) {}

    public function getDoctrineEdit($doctrine_id)
    {
        $selected = [];
        $unselected = [];
        $doctrine_fits = [];

        $fittings = Fitting::all();
        $doctrine_fittings = Doctrine::find($doctrine_id)->fittings()->get();

        foreach ($doctrine_fittings as $doctrine_fitting) {
            array_push($doctrine_fits, $doctrine_fitting->fitting_id);
        }

        foreach ($fittings as $fitting) {
            $entry = [
                'id' => $fitting->fitting_id,
                'shiptype' => $fitting->ship->typeName,
                'fitname' => $fitting->name,
                'typeID' => $fitting->ship->typeID,
            ];

            if (array_search($fitting->fitting_id, $doctrine_fits) !== false) {
                array_push($selected, $entry);
            } else {
                array_push($unselected, $entry);
            }
        }

        return [
            $selected,
            $unselected,
            $doctrine_id,
            Doctrine::find($doctrine_id)->name,
        ];
    }

    public function getDoctrineList()
    {
        $doctrine_names = [];

        $doctrines = Doctrine::all();

        if (count($doctrines) > 0) {

            foreach ($doctrines as $doctrine) {
                array_push($doctrine_names, [
                    'id' => $doctrine->id,
                    'name' => $doctrine->name,
                ]);
            }
        }

        return $doctrine_names;
    }

    public function getDoctrineById($id)
    {
        $fitting_list = [];

        $doctrine = Doctrine::find($id);
        $fittings = $doctrine->fittings()->get();

        foreach ($fittings as $fitting) {
            $ship = $fitting->ship;

            array_push($fitting_list, [
                'id' => $fitting->fitting_id,
                'name' => $fitting->name,
                'shipType' => $fitting->ship->typeName,
                'shipImg' => $ship->typeID,
            ]);
        }

        return $fitting_list;
    }

    public function delDoctrineById($id)
    {
        Doctrine::destroy($id);

        return 'Success';
    }

    public function deleteFittingById($id)
    {
        Fitting::destroy($id);

        return 'Success';
    }

    public function getSkillsByFitId($id)
    {
        $fitting = Fitting::findOrFail($id);

        return response()->json($this->personalSkillCheck->checkForCurrentUser($fitting));
    }

    public function getSkillsByDoctrineId($id)
    {
        $doctrine = Doctrine::findOrFail($id);

        return response()->json($this->personalSkillCheck->checkDoctrineForCurrentUser($doctrine));
    }

    protected function getFittings()
    {
        return Fitting::all();
    }

    public function getFittingList()
    {
        $fitnames = [];

        $fittings = $this->getFittings();

        if (count($fittings) <= 0) {
            return $fitnames;
        }

        foreach ($fittings as $fit) {
            array_push($fitnames, [
                'id' => $fit->fitting_id,
                'shiptype' => $fit->ship->typeName,
                'fitname' => $fit->name,
                'typeID' => $fit->ship_type_id,
            ]);
        }

        return $fitnames;
    }

    public function getEftFittingById($id)
    {
        $fitting = Fitting::findOrFail($id);

        return $fitting->toEve();
    }

    public function getFittingById($id)
    {
        $fitting = Fitting::findOrFail($id);

        $response = $this->fittingParser($fitting);

        $response['exportLinks'] = collect(config('fitting.exportlinks'))->map(function ($link) use ($fitting) {
            return [
                'name' => $link['name'],
                'url' => isset($link['url']) ? $link['url']."?id=$fitting->fitting_id" : route($link['route'], ['id' => $fitting->fitting_id]),
            ];
        })->values();

        return response()->json($response);
    }

    public function getFittingView()
    {
        $fitlist = $this->getFittingList();
        $corps = $this->getVisibleCorporations();
        $doctrine_list = $this->getDoctrineList();
        $manage = false;

        return view('fitting::fitting', compact('fitlist', 'corps', 'doctrine_list', 'manage'));
    }

    public function getManageView()
    {
        $fitlist = $this->getFittingList();
        $corps = $this->getVisibleCorporations();
        $manage = true;

        return view('fitting::fitting', compact('fitlist', 'corps', 'manage'));
    }

    private function getVisibleCorporations(): array
    {
        $corps = [];

        if (Gate::allows('global.superuser')) {
            $corpnames = CorporationInfo::all();
        } else {
            $corpids = CharacterAffiliation::whereIn('character_id', auth()->user()->associatedCharacterIds())->select('corporation_id')->get()->toArray();
            $corpnames = CorporationInfo::whereIn('corporation_id', $corpids)->get();
        }

        foreach ($corpnames as $corp) {
            $corps[$corp->corporation_id] = $corp->name;
        }

        return $corps;
    }

    public function getDoctrineView($doctrine_id = null)
    {
        $doctrine_list = $this->getDoctrineList();

        return view('fitting::doctrine', compact('doctrine_list', 'doctrine_id'));
    }

    public function saveFitting(FittingValidation $request)
    {
        $fitting = new Fitting;

        if ($request->fitSelection > 0) {
            $fit = Fitting::createFromEve($request->eftfitting, $request->fitSelection);
        } else {
            $fit = Fitting::createFromEve($request->eftfitting);
        }

        $this->skillRequirementSync->syncCalculatedMinimumRequirements($fit);

        // dispatch an event so other plugins know that a fitting has updated
        FittingUpdated::dispatch($fit);

        $fitlist = $this->getFittingList();
        $corps = $this->getVisibleCorporations();
        $manage = true;

        return view('fitting::fitting', compact('fitlist', 'corps', 'manage'));
    }

    public function postFitting(FittingValidation $request)
    {
        abort(410);
    }

    private function fittingParser($fit)
    {
        $jsfit = [];

        $jsfit['eft'] = $fit->toEve();
        $jsfit['shipname'] = $fit->ship->typeName;
        $jsfit['fitname'] = $fit->name;
        $jsfit['dronebay'] = []; // Lets load fighters in here too xD
        foreach ($fit->items as $ls) {

            switch ($ls->flag) {
                case Fitting::BAY_DRONE:
                case Fitting::BAY_FIGHTER:
                    if (isset($jsfit['dronebay'][$ls->type_id])) {
                        $jsfit['dronebay'][$ls->type_id]['qty'] += $ls->quantity;
                    } else {
                        $jsfit['dronebay'][$ls->type_id] = ['qty' => $ls->quantity, 'name' => $ls->type->typeName];
                    }
                    break;

                case Fitting::BAY_CARGO: // Not included in the JS response :)
                    break;

                default:
                    $jsfit[$ls->invFlag->flagName] = ['id' => $ls->type_id, 'name' => $ls->type->typeName];
                    break;
            }

        }

        return $jsfit;
    }

    public function postSkills(FittingValidation $request)
    {
        abort(410);
    }

    public function getFittingRequirements($id)
    {
        $fitting = Fitting::findOrFail($id);

        return response()->json([
            'minimum' => $this->personalSkillCheck->requirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM),
            'advanced' => $this->personalSkillCheck->requirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED),
        ]);
    }

    public function saveFittingRequirements(Request $request, $id)
    {
        $request->validate([
            'minimum' => 'array',
            'minimum.*.skill_type_id' => 'required|integer',
            'minimum.*.level' => 'required|integer|min:1|max:5',
            'minimum.*.source' => 'nullable|string',
            'minimum.*.notes' => 'nullable|string',
            'advanced' => 'array',
            'advanced.*.skill_type_id' => 'required|integer',
            'advanced.*.level' => 'required|integer|min:1|max:5',
            'advanced.*.source' => 'nullable|string',
            'advanced.*.notes' => 'nullable|string',
        ]);

        $fitting = Fitting::findOrFail($id);
        $minimum = $request->input('minimum', []);
        $advanced = $request->input('advanced', []);
        $requirements = collect($minimum)->merge($advanced);
        $skillIds = $requirements->pluck('skill_type_id')->unique()->values()->all();
        $knownSkillIds = InvType::whereIn('typeID', $skillIds)->pluck('typeID')->all();

        abort_if(count($knownSkillIds) !== count($skillIds), 422, 'Unknown skill type id.');

        $this->skillRequirementSync->replaceRequirements($fitting, FittingSkillRequirement::TIER_MINIMUM, $minimum);
        $this->skillRequirementSync->replaceRequirements($fitting, FittingSkillRequirement::TIER_ADVANCED, $advanced);

        return $this->getFittingRequirements($id);
    }

    public function saveDoctrine(DoctrineValidation $request)
    {
        $doctrine = new Doctrine;

        if ($request->doctrineid > 0) {
            $doctrine = Doctrine::find($request->doctrineid);
        }

        $doctrine->name = $request->doctrinename;
        $doctrine->save();

        foreach ($request->selectedFits as $fitId) {
            $doctrine->fittings()->sync($request->selectedFits);
        }

        DoctrineUpdated::dispatch($doctrine);

        return redirect()->route('cryptafitting::doctrineview');
    }

    public function viewDoctrineReport()
    {
        $doctrines = Doctrine::all();
        $corps = CorporationInfo::all();
        $alliances = [];

        $allids = [];

        foreach ($corps as $corp) {
            if (! is_null($corp->alliance_id)) {
                array_push($allids, $corp->alliance_id);
            }
        }

        $alliances = Alliance::whereIn('alliance_id', $allids)->get();

        return view('fitting::doctrinereport', compact('doctrines', 'corps', 'alliances'));
    }

    public function runReport(Request $request)
    {
        $request->validate([
            'alliances' => 'present|array',
            'alliances.*' => 'integer',
            'corporations' => 'present|array',
            'corporations.*' => 'integer',
            'doctrine' => 'required|integer',
        ]);

        return response()->json($this->corporationSkillReport->run(
            $request->alliances,
            $request->corporations,
            $request->doctrine,
        ));
    }
}
