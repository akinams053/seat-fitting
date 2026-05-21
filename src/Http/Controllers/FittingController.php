<?php

namespace CryptaTech\Seat\Fitting\Http\Controllers;

use CryptaTech\Seat\Fitting\Events\DoctrineUpdated;
use CryptaTech\Seat\Fitting\Events\FittingUpdated;
use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingItem;
use CryptaTech\Seat\Fitting\Models\FittingSkillPlan;
use CryptaTech\Seat\Fitting\Models\FittingSkillPlanAttachment;
use CryptaTech\Seat\Fitting\Models\FittingSkillPlanItem;
use CryptaTech\Seat\Fitting\Models\FittingSkillRequirement;
use CryptaTech\Seat\Fitting\Services\CorporationSkillReportService;
use CryptaTech\Seat\Fitting\Services\FleetSkillReviewService;
use CryptaTech\Seat\Fitting\Services\PersonalSkillCheckService;
use CryptaTech\Seat\Fitting\Services\SkillPlanParser;
use CryptaTech\Seat\Fitting\Services\SkillRequirementSyncService;
use CryptaTech\Seat\Fitting\Validation\DoctrineValidation;
use CryptaTech\Seat\Fitting\Validation\FittingValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Seat\Eveapi\Models\Alliances\Alliance;
use Seat\Eveapi\Models\Character\CharacterAffiliation;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Sde\InvGroup;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Web\Http\Controllers\Controller;

class FittingController extends Controller
{
    public function __construct(
        private PersonalSkillCheckService $personalSkillCheck,
        private SkillRequirementSyncService $skillRequirementSync,
        private CorporationSkillReportService $corporationSkillReport,
        private FleetSkillReviewService $fleetSkillReview,
        private SkillPlanParser $skillPlanParser,
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
        $doctrine = Doctrine::find($id);
        if ($doctrine) {
            $this->abortIfDoctrineLocked($doctrine);
        }
        DB::transaction(function () use ($id) {
            /* Polymorphic attachments have no DB-level FK back to doctrine — clean manually
               to avoid orphan rows that would still be returned by skillPlans()/attachments queries. */
            FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_DOCTRINE)
                ->where('attachable_id', (int) $id)
                ->delete();
            FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
                ->where('scope_doctrine_id', (int) $id)
                ->delete();
            Doctrine::destroy($id);
        });

        return 'Success';
    }

    public function deleteFittingById($id)
    {
        DB::transaction(function () use ($id) {
            FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
                ->where('attachable_id', (int) $id)
                ->delete();
            Fitting::destroy($id);
        });

        return 'Success';
    }

    public function getSkillsByFitId($id, Request $request)
    {
        $fitting = Fitting::findOrFail($id);
        $doctrineId = $request->query('doctrineId');
        $contextDoctrineId = $doctrineId !== null && $doctrineId !== '' ? (int) $doctrineId : null;

        return response()->json($this->personalSkillCheck->checkForCurrentUser($fitting, $contextDoctrineId));
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

    public function getFittingTree()
    {
        $fittings = Fitting::with(['ship'])->orderBy('name')->get();

        $fittingPayloads = [];
        foreach ($fittings as $fit) {
            $fittingPayloads[$fit->fitting_id] = [
                'id' => $fit->fitting_id,
                'name' => $fit->name,
                'shipType' => $fit->ship?->typeName,
                'typeID' => $fit->ship_type_id,
            ];
        }

        $doctrines = Doctrine::with('fittings:fitting_id')->orderBy('name')->get();
        $groups = [];
        $assignedFitIds = [];

        foreach ($doctrines as $doctrine) {
            $fitIds = $doctrine->fittings->pluck('fitting_id')->all();
            $assignedFitIds = array_merge($assignedFitIds, $fitIds);

            $items = [];
            foreach ($fitIds as $fid) {
                if (isset($fittingPayloads[$fid])) {
                    $items[] = $fittingPayloads[$fid];
                }
            }
            usort($items, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            $groups[] = [
                'id' => $doctrine->id,
                'name' => $doctrine->name,
                'fittings' => $items,
            ];
        }

        $assignedFitIds = array_unique($assignedFitIds);
        $ungrouped = [];
        foreach ($fittingPayloads as $fid => $payload) {
            if (! in_array($fid, $assignedFitIds, true)) {
                $ungrouped[] = $payload;
            }
        }
        usort($ungrouped, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        if (! empty($ungrouped)) {
            $groups[] = [
                'id' => null,
                'name' => trans('fitting::fitting.tree_ungrouped_label'),
                'fittings' => $ungrouped,
            ];
        }

        return response()->json($groups);
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
        $jsfit['dronebay'] = [];
        $jsfit['cargo'] = [];
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

                case Fitting::BAY_CARGO:
                    if (isset($jsfit['cargo'][$ls->type_id])) {
                        $jsfit['cargo'][$ls->type_id]['qty'] += $ls->quantity;
                    } else {
                        $jsfit['cargo'][$ls->type_id] = ['qty' => $ls->quantity, 'name' => $ls->type->typeName];
                    }
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
        $minimum = $this->personalSkillCheck->requirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM);
        $advanced = $this->personalSkillCheck->requirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED);
        /* Editor sees normalized values so dropdown filter and stored data agree. */
        $advanced = $this->personalSkillCheck->normalizeAdvancedAgainstMinimum($minimum, $advanced);

        return response()->json([
            'minimum' => $minimum,
            'advanced' => $advanced,
        ]);
    }

    public function getSkillGroups()
    {
        $groups = InvGroup::where('categoryID', InvGroup::SKILL_CATEGORY_ID)
            ->where('published', true)
            ->whereHas('types', function ($types) {
                $types->where('published', true);
            })
            ->orderBy('groupName')
            ->get(['groupID', 'groupName'])
            ->map(function (InvGroup $group) {
                return [
                    'id' => $group->groupID,
                    'text' => $group->groupName,
                    'name' => $group->groupName,
                ];
            })
            ->values();

        return response()->json($groups);
    }

    public function searchSkills(Request $request)
    {
        $request->validate([
            'group_id' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
        ]);

        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->input('q', ''));
        $skills = InvType::where('published', true)
            ->whereHas('group', function ($group) {
                $group->where('categoryID', InvGroup::SKILL_CATEGORY_ID)
                    ->where('published', true);
            })
            ->when($request->filled('group_id'), function ($query) use ($request) {
                $query->where('groupID', (int) $request->input('group_id'));
            })
            ->when($term !== '', function ($query) use ($term) {
                $query->where('typeName', 'like', "%{$term}%");
            })
            ->orderBy('typeName')
            ->limit(50)
            ->get(['typeID', 'typeName', 'groupID'])
            ->map(function (InvType $skill) {
                return [
                    'id' => $skill->typeID,
                    'text' => $skill->typeName,
                    'typeId' => $skill->typeID,
                    'typeName' => $skill->typeName,
                    'groupId' => $skill->groupID,
                ];
            })
            ->values();

        return response()->json([
            'results' => $skills,
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

        /* Auto-raise advanced level whenever the matching minimum is higher, so the stored
           data stays consistent with the displayed/checked state. Editor's dropdown filter
           already prevents most inversions on the way in; this is the safety net for the
           "raised minimum after advanced was set" path. */
        $advanced = $this->normalizeAdvancedPayloadAgainstMinimum($minimum, $advanced);

        $requirements = collect($minimum)->merge($advanced);
        $skillIds = $requirements->pluck('skill_type_id')->map(function ($skillId) {
            return (int) $skillId;
        })->unique()->values()->all();
        $knownSkillIds = InvType::whereIn('typeID', $skillIds)
            ->whereHas('group', function ($group) {
                $group->where('categoryID', InvGroup::SKILL_CATEGORY_ID);
            })
            ->pluck('typeID')
            ->all();

        abort_if(count($knownSkillIds) !== count($skillIds), 422, 'Unknown skill type id.');

        $this->skillRequirementSync->replaceRequirements($fitting, FittingSkillRequirement::TIER_MINIMUM, $minimum);
        $this->skillRequirementSync->replaceRequirements($fitting, FittingSkillRequirement::TIER_ADVANCED, $advanced);

        return $this->getFittingRequirements($id);
    }

    /**
     * Save-side mirror of PersonalSkillCheckService::normalizeAdvancedAgainstMinimum, but
     * operating on the request payload shape (skill_type_id instead of typeId).
     */
    private function normalizeAdvancedPayloadAgainstMinimum(array $minimum, array $advanced): array
    {
        if (empty($minimum) || empty($advanced)) {
            return $advanced;
        }

        $minByType = collect($minimum)->keyBy('skill_type_id');

        foreach ($advanced as &$advReq) {
            $minReq = $minByType->get($advReq['skill_type_id']);
            if ($minReq && (int) $minReq['level'] > (int) $advReq['level']) {
                $advReq['level'] = (int) $minReq['level'];
            }
        }
        unset($advReq);

        return $advanced;
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

    public function getDoctrineWorkspace()
    {
        $fittings = Fitting::with('ship')->orderBy('name')->get();

        $allPlans = FittingSkillPlan::orderBy('name')->get();
        $planPool = $allPlans->map(fn (FittingSkillPlan $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'tier' => $p->tier,
        ])->values();

        $plansById = $allPlans->keyBy('id');

        /* Pre-group ALL plan attachments by target to avoid N+1 when rendering pool / groups. */
        $allAttachments = FittingSkillPlanAttachment::all();
        $planAttachmentsByDoctrineId = $allAttachments
            ->where('attachable_type', FittingSkillPlan::ATTACHABLE_DOCTRINE)
            ->groupBy('attachable_id');
        $planAttachmentsByFittingId = $allAttachments
            ->where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
            ->groupBy('attachable_id');

        /* Per-fit plan list for a fit in a SPECIFIC doctrine: include scope NULL (universal) +
           scope = that doctrine. Pool fits don't get a plans list — pool has no doctrine context
           so per-fit attaches can't meaningfully target it. */
        $fitPlansInDoctrine = function (int $fittingId, int $doctrineId) use ($planAttachmentsByFittingId, $plansById) {
            $rows = $planAttachmentsByFittingId->get($fittingId) ?? collect();

            return $rows
                ->filter(fn ($r) => $r->scope_doctrine_id === null || (int) $r->scope_doctrine_id === $doctrineId)
                ->map(function ($r) use ($plansById) {
                    $p = $plansById->get($r->plan_id);

                    return $p ? [
                        'id' => $p->id,
                        'name' => $p->name,
                        'tier' => $p->tier,
                        'scope_doctrine_id' => $r->scope_doctrine_id !== null ? (int) $r->scope_doctrine_id : null,
                    ] : null;
                })
                ->filter()
                ->values()
                ->all();
        };

        $pool = $fittings->map(fn ($f) => [
            'id' => $f->fitting_id,
            'name' => $f->name,
            'shipType' => $f->ship?->typeName,
            'typeID' => $f->ship_type_id,
        ])->values();

        $doctrines = Doctrine::with('fittings:fitting_id')->orderBy('name')->get();
        $byId = $fittings->keyBy('fitting_id');
        $groups = $doctrines->map(function (Doctrine $d) use ($byId, $planAttachmentsByDoctrineId, $plansById, $fitPlansInDoctrine) {
            $items = $d->fittings->map(function ($pivotFit) use ($byId, $d, $fitPlansInDoctrine) {
                $f = $byId->get($pivotFit->fitting_id);
                if (! $f) {
                    return null;
                }

                return [
                    'id' => $f->fitting_id,
                    'name' => $f->name,
                    'shipType' => $f->ship?->typeName,
                    'typeID' => $f->ship_type_id,
                    'plans' => $fitPlansInDoctrine($f->fitting_id, $d->id),
                ];
            })->filter()->values();

            $attachedPlanIds = ($planAttachmentsByDoctrineId->get($d->id) ?? collect())
                ->pluck('plan_id');
            $plans = $attachedPlanIds->map(function ($pid) use ($plansById) {
                $p = $plansById->get($pid);

                return $p ? ['id' => $p->id, 'name' => $p->name, 'tier' => $p->tier] : null;
            })->filter()->values();

            return [
                'id' => $d->id,
                'name' => $d->name,
                'is_locked' => (bool) $d->is_locked,
                'fittings' => $items,
                'plans' => $plans,
            ];
        })->values();

        return response()->json([
            'groups' => $groups,
            'pool' => $pool,
            'planPool' => $planPool,
        ]);
    }

    public function createDoctrine(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
        ]);

        $doctrine = Doctrine::create(['name' => $data['name']]);
        DoctrineUpdated::dispatch($doctrine);

        return response()->json([
            'id' => $doctrine->id,
            'name' => $doctrine->name,
            'fittings' => [],
        ]);
    }

    public function renameDoctrine(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
        ]);

        $doctrine = Doctrine::findOrFail($id);
        $this->abortIfDoctrineLocked($doctrine);
        $doctrine->name = $data['name'];
        $doctrine->save();

        DoctrineUpdated::dispatch($doctrine);

        return response()->json([
            'id' => $doctrine->id,
            'name' => $doctrine->name,
        ]);
    }

    public function deleteDoctrine($id)
    {
        $doctrine = Doctrine::findOrFail($id);
        $this->abortIfDoctrineLocked($doctrine);
        DB::transaction(function () use ($doctrine) {
            FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_DOCTRINE)
                ->where('attachable_id', $doctrine->id)
                ->delete();
            /* Doctrine-scoped per-fit attachments become orphans when the doctrine vanishes — clean. */
            FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
                ->where('scope_doctrine_id', $doctrine->id)
                ->delete();
            $doctrine->fittings()->detach();
            $doctrine->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Toggle the is_locked flag on a doctrine. Locking freezes the doctrine — every mutating
     * endpoint (rename, delete, fitting add/remove, plan attach/detach at group or per-fit
     * scope) refuses with 423 until the doctrine is unlocked again. Reads (personal & corp
     * checks) are untouched. Permission: fitting.lock_doctrine, gated at the route.
     */
    public function toggleDoctrineLock($id)
    {
        $doctrine = Doctrine::findOrFail($id);
        $doctrine->is_locked = ! $doctrine->is_locked;
        $doctrine->save();

        return response()->json([
            'id' => $doctrine->id,
            'is_locked' => (bool) $doctrine->is_locked,
        ]);
    }

    private function abortIfDoctrineLocked(Doctrine $doctrine): void
    {
        abort_if($doctrine->is_locked, 423, trans('fitting::doctrine.locked_error'));
    }

    public function attachFittingToDoctrine($id, $fittingId)
    {
        $doctrine = Doctrine::findOrFail($id);
        $this->abortIfDoctrineLocked($doctrine);
        $fitting = Fitting::findOrFail($fittingId);
        $doctrine->fittings()->syncWithoutDetaching([$fitting->fitting_id]);

        DoctrineUpdated::dispatch($doctrine);

        return response()->json(['ok' => true]);
    }

    public function detachFittingFromDoctrine($id, $fittingId)
    {
        $doctrine = Doctrine::findOrFail($id);
        $this->abortIfDoctrineLocked($doctrine);
        DB::transaction(function () use ($doctrine, $fittingId) {
            /* Drop scoped per-fit attachments for this (fitting, doctrine) pair so
               the now-removed fit doesn't keep ghost requirements via that doctrine. */
            FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
                ->where('attachable_id', (int) $fittingId)
                ->where('scope_doctrine_id', $doctrine->id)
                ->delete();
            $doctrine->fittings()->detach((int) $fittingId);
        });

        DoctrineUpdated::dispatch($doctrine);

        return response()->json(['ok' => true]);
    }

    public function viewDoctrineReport()
    {
        $target = $this->resolveFixedReportTarget();
        $doctrines = Doctrine::with(['fittings' => function ($q) {
            $q->with('ship')->orderBy('name');
        }])->orderBy('name')->get();

        /* fittingsByDoctrine[doctrineId] = [{id, name, shipType}, ...] so the front-end
           narrows the fitting dropdown based on the selected doctrine. */
        $fittingsByDoctrine = $doctrines->mapWithKeys(function (Doctrine $d) {
            return [$d->id => $d->fittings->map(fn ($f) => [
                'id' => $f->fitting_id,
                'name' => $f->name,
                'shipType' => $f->ship?->typeName,
            ])->values()->all()];
        });

        return view('fitting::doctrinereport', [
            'doctrines' => $doctrines,
            'fittingsByDoctrine' => $fittingsByDoctrine,
            'targetAlliance' => $target['alliance'],
            'targetCorporation' => $target['corporation'],
        ]);
    }

    public function runReport(Request $request)
    {
        $request->validate([
            'doctrine' => 'required|integer',
            'fitting' => 'nullable|integer',
        ]);

        $target = $this->resolveFixedReportTarget();
        $allianceIds = [$target['alliance']->alliance_id];
        $corporationIds = [$target['corporation']->corporation_id];
        $doctrineId = (int) $request->input('doctrine');

        if ($request->filled('fitting')) {
            /* Doctrine + specific fit: scope plan inheritance to that doctrine, only run that fit. */
            $fitting = Fitting::with('ship')->where('fitting_id', (int) $request->input('fitting'))->firstOrFail();

            return response()->json($this->corporationSkillReport->runForFittings(
                $allianceIds,
                $corporationIds,
                collect([$fitting]),
                $doctrineId
            ));
        }

        return response()->json($this->corporationSkillReport->run(
            $allianceIds,
            $corporationIds,
            $doctrineId
        ));
    }

    public function viewFleetReview()
    {
        $doctrines = Doctrine::with(['fittings' => function ($q) {
            $q->with('ship')->orderBy('name');
        }])->orderBy('name')->get();

        $fittingsByDoctrine = $doctrines->mapWithKeys(function (Doctrine $d) {
            return [$d->id => $d->fittings->map(fn ($f) => [
                'id' => $f->fitting_id,
                'name' => $f->name,
                'shipType' => $f->ship?->typeName,
            ])->values()->all()];
        });

        return view('fitting::fleetreview', [
            'doctrines' => $doctrines,
            'fittingsByDoctrine' => $fittingsByDoctrine,
        ]);
    }

    public function runFleetReview(Request $request)
    {
        $request->validate([
            'fleet_id' => 'required|integer|min:1',
            'doctrine' => 'required|integer',
            'fitting' => 'nullable|integer',
        ]);

        try {
            return response()->json($this->fleetSkillReview->run(
                (int) $request->input('fleet_id'),
                (int) $request->input('doctrine'),
                $request->filled('fitting') ? (int) $request->input('fitting') : null
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function resolveFixedReportTarget(): array
    {
        $alliance = Alliance::where('ticker', 'FRT')->first();
        abort_if($alliance === null, 500, trans('fitting::doctrine.report_alliance_not_found'));

        $corporation = CorporationInfo::where('name', 'YeLuo-XingHai')
            ->where('alliance_id', $alliance->alliance_id)
            ->first();

        if ($corporation === null) {
            $corporation = CorporationInfo::where('name', 'YeLuo-XingHai')->first();
        }

        abort_if($corporation === null, 500, trans('fitting::doctrine.report_corporation_not_found'));

        return ['alliance' => $alliance, 'corporation' => $corporation];
    }

    /* ====================================================================
     *  Auxiliary skill plans
     * ==================================================================== */

    public function listPlans()
    {
        $plans = FittingSkillPlan::with('items.skill')->orderBy('name')->get();

        $attachmentsByPlan = FittingSkillPlanAttachment::all()
            ->groupBy('plan_id')
            ->map(function ($rows) {
                $byKind = $rows->groupBy('attachable_type');

                return [
                    'fittings' => $byKind->get(FittingSkillPlan::ATTACHABLE_FITTING, collect())
                        ->pluck('attachable_id')->map(fn ($id) => (int) $id)->values()->all(),
                    'doctrines' => $byKind->get(FittingSkillPlan::ATTACHABLE_DOCTRINE, collect())
                        ->pluck('attachable_id')->map(fn ($id) => (int) $id)->values()->all(),
                ];
            });

        return response()->json($plans->map(function (FittingSkillPlan $plan) use ($attachmentsByPlan) {
            $attachments = $attachmentsByPlan->get($plan->id, ['fittings' => [], 'doctrines' => []]);

            return $this->planResponseShape($plan, $attachments);
        })->values());
    }

    public function previewPlan(Request $request)
    {
        $data = $request->validate([
            'raw' => 'required|string|max:65535',
        ]);

        $parsed = $this->skillPlanParser->parse($data['raw']);

        return response()->json([
            'items' => $parsed['items'],
            'unmatched' => $parsed['unmatched'],
        ]);
    }

    public function createPlan(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'tier' => 'required|in:minimum,advanced',
            'description' => 'nullable|string|max:1000',
            'raw' => 'required|string|max:65535',
        ]);

        $parsed = $this->skillPlanParser->parse($data['raw']);
        abort_if(empty($parsed['items']), 422, 'Skill plan contains no recognisable skills.');

        $plan = DB::transaction(function () use ($data, $parsed) {
            $plan = FittingSkillPlan::create([
                'name' => $data['name'],
                'tier' => $data['tier'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($parsed['items'] as $item) {
                FittingSkillPlanItem::create([
                    'plan_id' => $plan->id,
                    'skill_type_id' => $item['type_id'],
                    'level' => $item['level'],
                ]);
            }

            return $plan;
        });

        return response()->json($this->planResponseShape(
            $plan->load('items.skill'),
            ['fittings' => [], 'doctrines' => []],
            ['unmatched' => $parsed['unmatched']]
        ));
    }

    public function getPlan($id)
    {
        $plan = FittingSkillPlan::with('items.skill')->findOrFail($id);
        $attachments = $this->planAttachmentsArray($plan->id);

        return response()->json($this->planResponseShape($plan, $attachments));
    }

    public function updatePlan(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'tier' => 'sometimes|required|in:minimum,advanced',
            'description' => 'nullable|string|max:1000',
            'raw' => 'sometimes|nullable|string|max:65535',
        ]);

        $plan = FittingSkillPlan::findOrFail($id);
        $unmatched = [];

        DB::transaction(function () use ($plan, $data, &$unmatched) {
            if (array_key_exists('name', $data)) {
                $plan->name = $data['name'];
            }
            if (array_key_exists('tier', $data)) {
                $plan->tier = $data['tier'];
            }
            if (array_key_exists('description', $data)) {
                $plan->description = $data['description'];
            }
            $plan->save();

            if (! empty($data['raw'])) {
                $parsed = $this->skillPlanParser->parse($data['raw']);
                abort_if(empty($parsed['items']), 422, 'Skill plan contains no recognisable skills.');
                $unmatched = $parsed['unmatched'];

                FittingSkillPlanItem::where('plan_id', $plan->id)->delete();
                foreach ($parsed['items'] as $item) {
                    FittingSkillPlanItem::create([
                        'plan_id' => $plan->id,
                        'skill_type_id' => $item['type_id'],
                        'level' => $item['level'],
                    ]);
                }
            }
        });

        $plan->load('items.skill');
        $attachments = $this->planAttachmentsArray($plan->id);

        return response()->json($this->planResponseShape($plan, $attachments, ['unmatched' => $unmatched]));
    }

    public function deletePlan($id)
    {
        $plan = FittingSkillPlan::findOrFail($id);
        $plan->delete();

        return response()->json(['ok' => true]);
    }

    public function attachPlanToFitting($id, $fittingId)
    {
        FittingSkillPlan::findOrFail($id);
        Fitting::findOrFail($fittingId);

        FittingSkillPlanAttachment::firstOrCreate([
            'plan_id' => (int) $id,
            'attachable_type' => FittingSkillPlan::ATTACHABLE_FITTING,
            'attachable_id' => (int) $fittingId,
            'scope_doctrine_id' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function detachPlanFromFitting($id, $fittingId)
    {
        FittingSkillPlanAttachment::where('plan_id', (int) $id)
            ->where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
            ->where('attachable_id', (int) $fittingId)
            ->whereNull('scope_doctrine_id')
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function attachPlanToFittingInDoctrine($id, $fittingId, $doctrineId)
    {
        FittingSkillPlan::findOrFail($id);
        Fitting::findOrFail($fittingId);
        $doctrine = Doctrine::findOrFail($doctrineId);
        $this->abortIfDoctrineLocked($doctrine);

        FittingSkillPlanAttachment::firstOrCreate([
            'plan_id' => (int) $id,
            'attachable_type' => FittingSkillPlan::ATTACHABLE_FITTING,
            'attachable_id' => (int) $fittingId,
            'scope_doctrine_id' => (int) $doctrineId,
        ]);

        return response()->json(['ok' => true]);
    }

    public function detachPlanFromFittingInDoctrine($id, $fittingId, $doctrineId)
    {
        $doctrine = Doctrine::find($doctrineId);
        if ($doctrine) {
            $this->abortIfDoctrineLocked($doctrine);
        }
        FittingSkillPlanAttachment::where('plan_id', (int) $id)
            ->where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
            ->where('attachable_id', (int) $fittingId)
            ->where('scope_doctrine_id', (int) $doctrineId)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function attachPlanToDoctrine($id, $doctrineId)
    {
        FittingSkillPlan::findOrFail($id);
        $doctrine = Doctrine::findOrFail($doctrineId);
        $this->abortIfDoctrineLocked($doctrine);

        FittingSkillPlanAttachment::firstOrCreate([
            'plan_id' => (int) $id,
            'attachable_type' => FittingSkillPlan::ATTACHABLE_DOCTRINE,
            'attachable_id' => (int) $doctrineId,
        ]);

        return response()->json(['ok' => true]);
    }

    public function detachPlanFromDoctrine($id, $doctrineId)
    {
        $doctrine = Doctrine::find($doctrineId);
        if ($doctrine) {
            $this->abortIfDoctrineLocked($doctrine);
        }
        FittingSkillPlanAttachment::where('plan_id', (int) $id)
            ->where('attachable_type', FittingSkillPlan::ATTACHABLE_DOCTRINE)
            ->where('attachable_id', (int) $doctrineId)
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function planAttachmentsArray(int $planId): array
    {
        $rows = FittingSkillPlanAttachment::where('plan_id', $planId)->get();

        return [
            'fittings' => $rows->where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
                ->pluck('attachable_id')->map(fn ($id) => (int) $id)->values()->all(),
            'doctrines' => $rows->where('attachable_type', FittingSkillPlan::ATTACHABLE_DOCTRINE)
                ->pluck('attachable_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    private function planResponseShape(FittingSkillPlan $plan, array $attachments, array $extra = []): array
    {
        return array_merge([
            'id' => $plan->id,
            'name' => $plan->name,
            'tier' => $plan->tier,
            'description' => $plan->description,
            'items' => $plan->items->map(fn (FittingSkillPlanItem $item) => [
                'type_id' => (int) $item->skill_type_id,
                'type_name' => $item->skill->typeName ?? '',
                'level' => (int) $item->level,
            ])->values()->all(),
            'attachments' => $attachments,
        ], $extra);
    }

    /* ====================================================================
     *  Fitting copy + rename
     * ==================================================================== */

    public function copyFitting($id)
    {
        $source = Fitting::with(['items', 'skillRequirements'])->findOrFail($id);

        $copy = DB::transaction(function () use ($source) {
            $copy = new Fitting;
            $copy->name = $this->buildCopyName($source->name);
            $copy->description = $source->description;
            $copy->ship_type_id = $source->ship_type_id;
            $copy->save();

            foreach ($source->items as $item) {
                $newItem = new FittingItem;
                $newItem->fitting_id = $copy->fitting_id;
                $newItem->type_id = $item->type_id;
                $newItem->quantity = $item->quantity;
                $newItem->flag = $item->flag;
                $newItem->save();
            }

            foreach ($source->skillRequirements as $req) {
                FittingSkillRequirement::create([
                    'fitting_id' => $copy->fitting_id,
                    'skill_type_id' => $req->skill_type_id,
                    'tier' => $req->tier,
                    'level' => $req->level,
                    'source' => $req->source,
                    'is_active' => $req->is_active,
                    'notes' => $req->notes,
                ]);
            }

            /* Only clone universal (scope=null) direct attachments. Doctrine-scoped attachments
               belong to the source's specific (fit, doctrine) tuple and would point at a doctrine
               the copy isn't a member of yet — leave them with the original. */
            $universalDirectPlans = FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
                ->where('attachable_id', $source->fitting_id)
                ->whereNull('scope_doctrine_id')
                ->pluck('plan_id');

            foreach ($universalDirectPlans as $planId) {
                FittingSkillPlanAttachment::create([
                    'plan_id' => $planId,
                    'attachable_type' => FittingSkillPlan::ATTACHABLE_FITTING,
                    'attachable_id' => $copy->fitting_id,
                    'scope_doctrine_id' => null,
                ]);
            }

            return $copy;
        });

        FittingUpdated::dispatch($copy);

        return response()->json([
            'id' => $copy->fitting_id,
            'name' => $copy->name,
            'ship_type_id' => $copy->ship_type_id,
        ]);
    }

    public function renameFitting(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
        ]);

        $fitting = Fitting::findOrFail($id);
        $fitting->name = $data['name'];
        $fitting->save();

        FittingUpdated::dispatch($fitting);

        return response()->json([
            'id' => $fitting->fitting_id,
            'name' => $fitting->name,
        ]);
    }

    private function buildCopyName(string $base): string
    {
        $suffix = ' ('.trans('fitting::fitting.copy_suffix').')';
        $candidate = $base.$suffix;
        $n = 2;
        while (Fitting::where('name', $candidate)->exists()) {
            $candidate = $base.$suffix.' '.$n;
            $n++;
        }

        return $candidate;
    }
}
