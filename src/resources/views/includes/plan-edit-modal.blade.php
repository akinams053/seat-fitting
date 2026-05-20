<div class="modal fade" tabindex="-1" role="dialog" id="planEditModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h4 class="modal-title" id="planEditModalTitle">{{trans('fitting::fitting.plan_modal_title_new')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="planEditId" value="">

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="planEditName" class="small mb-1">{{trans('fitting::fitting.plan_name_label')}}</label>
                        <input type="text" id="planEditName" class="form-control form-control-sm" maxlength="191" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small mb-1 d-block">{{trans('fitting::fitting.plan_tier_label')}}</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="planEditTier" id="planEditTierMinimum" value="minimum" checked>
                            <label class="form-check-label" for="planEditTierMinimum">{{trans('fitting::fitting.tab_entry_label')}}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="planEditTier" id="planEditTierAdvanced" value="advanced">
                            <label class="form-check-label" for="planEditTierAdvanced">{{trans('fitting::fitting.tab_advanced_label')}}</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="planEditDescription" class="small mb-1">{{trans('fitting::fitting.plan_description_label')}}</label>
                    <input type="text" id="planEditDescription" class="form-control form-control-sm" maxlength="1000">
                </div>

                <div class="form-group">
                    <label for="planEditRaw" class="small mb-1">{{trans('fitting::fitting.plan_raw_label')}}</label>
                    <small class="text-muted d-block mb-1">{{trans('fitting::fitting.plan_raw_hint')}}</small>
                    <textarea id="planEditRaw" class="form-control form-control-sm" rows="10" style="font-family: monospace; font-size: 12px;"></textarea>
                </div>

                <div class="form-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="planParseBtn">
                        <i class="fa fa-search"></i> {{trans('fitting::fitting.plan_parse_btn')}}
                    </button>
                </div>

                <div id="planPreviewBlock" style="display:none;">
                    <h6 class="small text-muted">{{trans('fitting::fitting.plan_preview_title')}} (<span id="planPreviewCount">0</span>)</h6>
                    <div id="planPreviewItems" class="plan-preview-items"></div>
                    <div id="planPreviewUnmatchedBlock" style="display:none;">
                        <h6 class="small text-danger mt-2">{{trans('fitting::fitting.plan_unmatched_title')}}</h6>
                        <ul id="planPreviewUnmatched" class="plan-preview-unmatched"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{trans('fitting::global.cancel_btn')}}</button>
                <button type="button" class="btn btn-primary" id="planSaveBtn">{{trans('fitting::global.submit_btn')}}</button>
            </div>
        </div>
    </div>
</div>
