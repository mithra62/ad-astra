@include('_inc._header', ['title' => 'Edit User'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h5>Edit User</h5>
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
                            <a class="f-s-14 f-w-500" href="{{ route('users.edit', $user) }}">Edit User</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Breadcrumb end -->
        @include('_inc._message')

        <!-- Edit User start -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column gap-2">
                    <h5>Edit User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.update', $user) }}" class="row g-3 needs-validation"
                          novalidate>
                        @csrf
                        {{ method_field('PUT') }}
                        <div class="col-md-12">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control" id="name" required type="text"
                                   value="{{ old('name', $user->name) }}" name="name">
                            @error('name')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text" id="email">@</span>
                                <input aria-describedby="email" class="form-control"
                                       id="email"
                                       required type="text" name="email" value="{{ old('email', $user->email) }}">
                            </div>
                            @error('email')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="title">Title</label>
                            <input class="form-control" id="title" required type="text"
                                   value="{{ old('title', $user->title) }}" name="title">
                            @error('title')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input class="form-control" id="phone" required type="text"
                                   value="{{ old('phone', $user->phone) }}" name="phone">
                            @error('phone')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label" for="roles">Roles</label>
                            <select class="select-basic-multiple-four w-100 select_primary" multiple="multiple"
                                    name="roles[]" id="roles">
                                @foreach ($roles as $role)
                                    <option
                                        @if(old('roles'))
                                            @foreach(old('roles') as $key => $field)
                                                @if(old('roles.' . $key) == $role->name) selected @endif
                                        @endforeach
                                        @else
                                            @foreach($user->roles as $key => $field)
                                                @if($field->name == $role->name) selected @endif
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
                            <input type="hidden" name="user_id" value="{{ $user->id }}"/>
                            <button class="btn btn-primary float-end" type="submit">Edit User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Edit User end -->

        <!-- Change Password start -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column gap-2">
                    <h5>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.password', $user) }}" class="row g-3 needs-validation"
                          novalidate>
                        @csrf
                        {{ method_field('PUT') }}
                        <div class="col-md-12">
                            <label class="form-label" for="password">New Password</label>
                            <input class="form-control" id="password" required type="password" value="" name="password">
                            @error('password')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input class="form-control" id="password_confirmation" required type="password" value=""
                                   name="password_confirmation">
                            @error('password_confirmation')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="user_id" value="{{ $user->id }}"/>
                            <button class="btn btn-primary float-end" type="submit">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Change Password end -->

        @if($user->can('api'))
            <!-- Change Token Management start -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-column gap-2">
                        <div class="row">
                            <h5>Access Tokens</h5>
                            <div class="col-12">
                                <p class="float-right text-end">
                                    <a href="{{ route('users.token.create', $user) }}" class="btn btn-primary">Create
                                        Token</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Expires</th>
                                        <th scope="col">Last Used</th>
                                        <th scope="col">Last Modified</th>
                                        <th scope="col">Created</th>
                                        <th scope="col"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(Auth::user()->tokens->count() >= 1)
                                    @foreach ($user->tokens as $token)
                                        <tr>
                                            <td>{{ $token->name }}</td>
                                            <td class="f-w-500">
                                                @if($token->expires_at)
                                                    {{ $token->expires_at->format('F j, Y') }}
                                                @else
                                                    Never
                                                @endif
                                            </td>
                                            <td class="text-secondary f-w-600">
                                                @if($token->last_used_at)
                                                    {{ $token->last_used_at->format('F j, Y') }}
                                                @else
                                                    Never
                                                @endif
                                            </td>
                                            <td class="text-secondary f-w-600">{{ $token->updated_at->format('F j, Y') }}</td>
                                            <td>{{ $token->created_at->format('F j, Y') }}</td>
                                            <td>
                                                <a class="btn btn-danger icon-btn b-r-4"
                                                   href="{{ route('users.token.confirm', ['id' => $user->id , 'token_id' => $token->id]) }}">
                                                    <i class="ti ti-trash"></i>
                                                </a>
                                                <a class="btn btn-success icon-btn b-r-4"
                                                   href="{{ route('users.token.edit', ['id' => $user->id , 'token_id' => $token->id]) }}">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6">
                                                <p class="text-center">
                                                    No Tokens Created.
                                                </p>
                                            </td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Change Token Management end -->
        @endif


    </div>
</main>

@include('_inc._footer')
