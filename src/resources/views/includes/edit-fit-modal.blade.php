<div class="modal fade" tabindex="-1" role="dialog" id="fitEditModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h4 class="modal-title">{{trans('fitting::fitting.edit_fitting_modal_title')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
            </div>
            <form role="form" action="{{ route('cryptafitting::saveFitting') }}" method="post">
                <input type="hidden" id="fitSelection" name="fitSelection" value="0">
                <div class="modal-body">
                    <p>{{trans('fitting::fitting.edit_fitting_body')}}</p>
                    {{ csrf_field() }}
                    <textarea name="eftfitting" id="eftfitting" rows="15" style="width: 100%"></textarea>
                    <div class="row" style="margin-top: 15px;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="minimum_dps">{{ trans('fitting::fitting.damage_metric_minimum_dps') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control damage-metric-input" id="minimum_dps" name="minimum_dps">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="minimum_dph">{{ trans('fitting::fitting.damage_metric_minimum_dph') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control damage-metric-input" id="minimum_dph" name="minimum_dph">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="advanced_dps">{{ trans('fitting::fitting.damage_metric_advanced_dps') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control damage-metric-input" id="advanced_dps" name="advanced_dps">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="advanced_dph">{{ trans('fitting::fitting.damage_metric_advanced_dph') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control damage-metric-input" id="advanced_dph" name="advanced_dph">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="btn-group pull-right" role="group">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{trans('fitting::global.cancel_btn')}}</button>
                        <input type="submit" class="btn btn-primary" id="savefitting" value="{{trans('fitting::global.submit_btn')}}"/>
                    </div>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->