@include('admin._inc._header', ['title' => 'Delete User'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h5>Users</h5>
                <div class="col-6 ">
                    <ul class="app-line-breadcrumbs mb-3">
                        <li class="">
                            <a class="f-s-14 f-w-500" href="{{ route('dashboard') }}">
                                <span>
                                    <i class="ph-duotone  ph-table f-s-16"></i> Dashboard
                                </span>
                            </a>
                        </li>
                        <li>
                            <a class="f-s-14 f-w-500" href="{{ route('users.index') }}">Users</a>
                        </li>
                        <li class="active">
                            <a class="f-s-14 f-w-500" href="{{ route('users.confirm', $user) }}">Delete User</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Breadcrumb end -->

        <!-- Blank start -->
        <div class="row">
            <!-- Default Card start -->
            <form method="POST" action="{{ route('users.destroy', $user) }}" class="row g-3 needs-validation" novalidate>
                @csrf
                {{ method_field('DELETE') }}
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Delete User</h5>
                        </div>
                        <div class="card-body">
                            <h6>Are you sure you want to remove this user?</h6>
                            <p class="text-secondary"> Are you sure you want to remove this user?</p>
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
                                <button class="btn btn-primary float-end" type="submit">Delete User</button>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

            <!-- Default Card end -->
        </div>
        <!-- Blank end -->
    </div>
</main>

@include('admin._inc._footer', ['status' => 'complete'])
