<div class="modal fade" tabindex="-1" role="dialog" id="fitRenameModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h4 class="modal-title">{{trans('fitting::fitting.fit_rename_modal_title')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fitRenameId" value="">
                <label for="fitRenameName" class="small mb-1">{{trans('fitting::fitting.fit_rename_name_label')}}</label>
                <input type="text" id="fitRenameName" class="form-control form-control-sm" maxlength="191" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{trans('fitting::global.cancel_btn')}}</button>
                <button type="button" class="btn btn-primary" id="fitRenameSaveBtn">{{trans('fitting::global.submit_btn')}}</button>
            </div>
        </div>
    </div>
</div>
