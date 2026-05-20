<div class="card card-primary card-solid" id="fitting-box">
    <div class="card-header">
        <h3 class="card-title" id="middle-header"></h3>
    </div>
    <input type="hidden" id="fittingId" value=""/>
    <div class="card-body">
        <div id="fitting-window" class="accordion">
            @foreach([
                'highSlots' => trans('fitting::fitting.fit_high_slot_title'),
                'midSlots' => trans('fitting::fitting.fit_mid_slot_title'),
                'lowSlots' => trans('fitting::fitting.fit_low_slot_title'),
                'rigs' => trans('fitting::fitting.fit_rigs_title'),
                'subSlots' => trans('fitting::fitting.fit_subsystems_title'),
                'drones' => trans('fitting::fitting.fit_drone_bay_title'),
                'cargo' => trans('fitting::fitting.fit_cargo_title'),
            ] as $slotId => $slotTitle)
                <div class="card mb-2">
                    <div class="card-header p-2" id="{{$slotId}}Heading">
                        <button class="btn btn-link btn-block text-left p-0" type="button" data-toggle="collapse" data-target="#{{$slotId}}Collapse" aria-expanded="false" aria-controls="{{$slotId}}Collapse">
                            {{$slotTitle}}
                        </button>
                    </div>
                    <div id="{{$slotId}}Collapse" class="collapse" aria-labelledby="{{$slotId}}Heading">
                        <div class="card-body p-2">
                            <table class="table table-condensed table-striped mb-0" id="{{$slotId}}">
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
