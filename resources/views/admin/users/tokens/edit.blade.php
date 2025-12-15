@include('_inc._header', ['title' => 'Edit User Token'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h5>Create User Token</h5>
                <div class="col-6 ">
                    <ul class="app-line-breadcrumbs mb-3">
                        <li class="">
                            <a class="f-s-14 f-w-500" href="{{ route('dashboard') }}">
                                <span>
                                    <i class="ph-duotone  ph-table f-s-16"></i> Dashboard
                                </span>
                            </a>
                        </li>
                        <li class="">
                            <a class="f-s-14 f-w-500" href="{{ route('users.index') }}">Users</a>
                        </li>
                        <li>
                            <a class="f-s-14 f-w-500" href="{{ route('users.edit', $user) }}">{{ $user->name }}</a>
                        </li>
                        <li class="active">
                            <a class="f-s-14 f-w-500" href="{{ route('users.token.create', $user) }}">Edit User Token</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Breadcrumb end -->

        <!-- Custom Styles start -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column gap-2">
                    <h5>Edit User Token</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.token.update', ['id' => $user->id , 'token_id' => $token]) }}" class="row g-3 needs-validation" novalidate>
                        @csrf
                        {{ method_field('PUT') }}
                        <div class="col-md-12">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control" id="name" required type="text" value="{{ old('name', $token->name) }}" name="name">
                            @error('name')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12 dates">
                            <label class="form-label" for="expires_at">Expires</label>
                            <input class="form-control basic-date" id="expires_at" required type="text" value="{{ old('expires_at', $token->expires_at) }}" name="expires_at" placeholder="YYYY-MM-DD">
                            @error('expires_at')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary float-end" type="submit">Edit User Token</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Custom Styles end -->


    </div>
</main>

@include('_inc._footer')
