<input type="hidden" id="fittingId" value=""/>

<div id="fitDetailEmpty" class="fit-detail-empty">
    {{trans('fitting::fitting.fit_select_hint')}}
</div>

<div id="fitDetail" style="display:none;">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0" id="fitDetailTitle"></h6>
        @if($manage ?? false)
            <button type="button" class="btn btn-xs btn-outline-secondary" id="eftexportTrigger">
                <i class="fa fa-code"></i> EFT
            </button>
        @endif
    </div>

    @foreach([
        'highSlots' => trans('fitting::fitting.fit_high_slot_title'),
        'midSlots' => trans('fitting::fitting.fit_mid_slot_title'),
        'lowSlots' => trans('fitting::fitting.fit_low_slot_title'),
        'rigs' => trans('fitting::fitting.fit_rigs_title'),
        'subSlots' => trans('fitting::fitting.fit_subsystems_title'),
        'drones' => trans('fitting::fitting.fit_drone_bay_title'),
        'cargo' => trans('fitting::fitting.fit_cargo_title'),
    ] as $slotId => $slotTitle)
        <div class="fit-detail-slot" data-slot="{{$slotId}}" data-expanded="false">
            <div class="fit-detail-slot-header" data-toggle-slot="{{$slotId}}">
                <span class="fit-detail-slot-chevron">▶</span>
                <span class="fit-detail-slot-title">{{$slotTitle}}</span>
                <span class="fit-detail-slot-count text-muted ml-auto"></span>
            </div>
            <div class="fit-detail-slot-body">
                <div class="fit-detail-slot-items" id="{{$slotId}}Items"></div>
            </div>
        </div>
    @endforeach
</div>
