@include('_inc._header', ['title' => 'Delete API Token'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h4 class="main-title">API Tokens</h4>
                <ul class="app-line-breadcrumbs mb-3">
                    <li class="">
                        <a class="f-s-14 f-w-500" href="{{ route('dashboard') }}">
                            <span>
                                <i class="ph-duotone  ph-table f-s-16"></i> Dashboard
                            </span>
                        </a>
                    </li>
                    <li class="">
                        <a class="f-s-14 f-w-500" href="{{ route('account.settings') }}">Account Settings</a>
                    </li>
                    <li class="">
                        <a class="f-s-14 f-w-500" href="{{ route('account.tokens.index') }}">API Tokens</a>
                    </li>
                    <li class="active">
                        <a class="f-s-14 f-w-500" href="{{ route('account.tokens.confirm', ['token_id' => $token]) }}">Delete API Token</a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Breadcrumb end -->
        @include('_inc._message')
        <!-- setting-app start -->
        <div class="row">
            @include('account._sidebar', ['active' => 'tokens'])

            <div class="col-lg-8 col-xxl-9">
                <div class="tab-content">

                    <!-- Change Token Management start -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-column gap-2">
                                <div class="row">
                                    <h5>Access Tokens</h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('account.tokens.destroy', ['token_id' => $token]) }}" class="row g-3 needs-validation" novalidate>
                                    @csrf
                                    {{ method_field('DELETE') }}
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Delete User Token</h5>
                                            </div>
                                            <div class="card-body">
                                                <h6>Are you sure you want to remove this API token?</h6>
                                                <p class="text-secondary">Deletes cannot be reversed!</p>
                                            </div>
                                            <div class="card-footer">
                                                <div class="switch-border-primary switch-primary switch-unchecked-primary my-3 card-body main-switch main-switch-color">
                                                    <input class="toggle switch-border-primary" name="confirm_removal" id="confirm_removal"
                                                           type="checkbox">
                                                    <label for="confirm_removal">Confirm Removal</label>
                                                    @error('confirm_removal')
                                                    <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="col-12 float-end">
                                                    <button class="btn btn-primary float-end" type="submit">Delete API Token</button>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- Change Token Management end -->



                </div>
            </div>
        </div>
        <!--setting app end -->
    </div>
</main>
@include('_inc._footer', ['status' => 'complete'])
