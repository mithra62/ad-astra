@include('_inc._header', ['title' => 'Edit API Token'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h4 class="main-title">Edit API Token</h4>
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
                        <a class="f-s-14 f-w-500" href="{{ route('account.tokens.edit', ['token_id' => $token]) }}">Edit API Token</a>
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
                                    <h5>Edit API Token</h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('account.tokens.update', ['token_id' => $token]) }}" class="row g-3 needs-validation" novalidate>
                                    @csrf
                                    {{ method_field('PUT') }}
                                    <div class="col-md-12">
                                        <label class="form-label" for="name">Name</label>
                                        <input class="form-control" id="name" required type="text" value="{{ old('name', $token->name) }}" name="name">
                                        @error('name')
                                        <p class="text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary float-end" type="submit">Edit API Token</button>
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
