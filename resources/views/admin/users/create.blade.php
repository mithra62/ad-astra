@include('_inc._header', ['title' => 'Create User'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h5>Create User</h5>
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
                        <li class="active">
                            <a class="f-s-14 f-w-500" href="{{ route('users.create') }}">Create User</a>
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
                    <h5>Create User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.store') }}" class="row g-3 needs-validation" novalidate>
                        @csrf
                        <div class="col-md-12">
                            <label class="form-label" for="validationCustom01">Name</label>
                            <input class="form-control" id="validationCustom01" required type="text" value="{{ old('name') }}" name="name">
                            @error('name')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="validationCustomUsername">Email</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text" id="inputGroupPrepend">@</span>
                                <input aria-describedby="inputGroupPrepend" class="form-control"
                                       id="validationCustomUsername"
                                       required type="text" name="email" value="{{ old('email') }}">
                            </div>
                            @error('email')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="validationCustom02">Title</label>
                            <input class="form-control" id="validationCustom02" required type="text" value="{{ old('title') }}" name="title">
                            @error('title')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input class="form-control" id="phone" required type="text" value="{{ old('phone') }}" name="phone">
                            @error('phone')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <h4>Password</h4>
                        <div class="col-md-12">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" required type="password" value="" name="password">
                            @error('password')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input class="form-control" id="password_confirmation" required type="password" value="" name="password_confirmation">
                            @error('password_confirmation')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label" for="roles">Roles</label>
                            <select class="select-basic-multiple-four w-100 select_primary" multiple="multiple" name="roles[]">
                                @foreach ($roles as $role)
                                <option
                                    @if(old('roles'))
                                    @foreach(old('roles') as $key => $field)
                                        @if(old('roles.' . $key) == $role->name) selected @endif
                                    @endforeach
                                    @endif
                                    value="{{ $role->name }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            @error('roles')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary float-end" type="submit">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Custom Styles end -->


    </div>
</main>

@include('_inc._footer')
