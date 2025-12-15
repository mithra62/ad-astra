@include('_inc._header', ['title' => 'Edit Role'])


<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h5>Edit Role</h5>
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
                            <a class="f-s-14 f-w-500" href="{{ route('roles.index') }}">Roles</a>
                        </li>
                        <li class="active">
                            <a class="f-s-14 f-w-500" href="{{ route('roles.edit', $role) }}">Edit Role</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Breadcrumb end -->

        <!-- Custom Styles start -->
        <form method="POST" action="{{ route('roles.update', $role) }}" class="row g-3 needs-validation" novalidate>
            @csrf
            {{ method_field('PUT') }}
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-column gap-2">
                        <h5>Edit Role</h5>
                    </div>
                    <div class="card-body">
                        <div class="col-md-12">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control" id="name" required type="text" value="{{ old('name', $role->name) }}"
                                   name="name">
                            @error('name')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="highlight">Highlight Color</label>
                            <input class="form-control" id="highlight" required type="color"
                                   value="{{ old('highlight', $role->highlight) }}" name="highlight"
                                   placeholder="Color for this role badge. Accepts HEX codes.">
                            @error('title')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" id="description" placeholder="Some text..."
                                      rows="4">{{ old('description', $role->description) }}</textarea>
                        </div>
                        <div class="col-md-12 mt-3">
                            <h6>Permissions</h6>
                            @error('permissions')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror

                            @foreach ($permissions as $permission)
                                <div class="col-md-6">
                                    <div class="switch-border-primary switch-primary switch-unchecked-primary my-3 card-body main-switch main-switch-color">
                                        <input class="toggle switch-border-primary" name="permissions[]"
                                               id="permission_{{ $permission->id }}"

                                               @if(old('permissions'))
                                                   @foreach(old('permissions') as $key => $field)
                                                       @if(old('permissions.' . $key) == $permission->name) checked @endif
                                                   @endforeach
                                               @else
                                                   @foreach($role->getPermissionNames() as $perm)
                                                       @if($perm == $permission->name) checked @endif
                                                   @endforeach
                                               @endif

                                               value="{{ $permission->name }}"
                                               type="checkbox">
                                        <label for="permission_{{ $permission->id }}">{{ $permission->name }}
                                            - {{ $permission->description }}</label>
                                    </div>
                                </div>
                            @endforeach
                            @error('permissions')
                            <p class="text-danger">{{ $message }}</p>
                            @enderror

                        </div>
                        <div class="col-12 mt-3">
                            <button class="btn btn-primary float-end" type="submit">Edit Role</button>
                        </div>

                    </div>
                </div>
            </div>
        </form>
        <!-- Custom Styles end -->


    </div>
</main>


@include('_inc._footer')
