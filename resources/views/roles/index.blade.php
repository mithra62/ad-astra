@include('_inc._header', ['title' => 'Roles'])
<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h5>Member Roles</h5>
                <div class="col-6 ">
                    <ul class="app-line-breadcrumbs mb-3">
                        <li class="">
                            <a class="f-s-14 f-w-500" href="{{ route('dashboard') }}">
                                <span>
                                    <i class="ph-duotone  ph-table f-s-16"></i> Dashboard
                                </span>
                            </a>
                        </li>
                        <li class="active">
                            <a class="f-s-14 f-w-500" href="{{ route('roles.index') }}">Roles</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Breadcrumb end -->
        @include('_inc._message')

        <div class="row table-section">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-6">
                                <h5>Manage Roles</h5>
                                <p>You can manage all User Roles from here.</p>
                            </div>
                            <div class="col-6">
                                <p class="float-right text-end">
                                    <a href="{{ route('roles.create') }}" class="btn btn-primary">Add Role</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Id</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Guard</th>
                                    <th scope="col">Last Modified</th>
                                    <th scope="col">Created</th>
                                    <th scope="col"></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($roles as $role)
                                    <tr>
                                        <td><a href="{{ route('roles.edit', $role) }}">{{ $role->id }}</a></td>
                                        <td>{{ $role->name }}</td>
                                        <td class="f-w-500">{{ $role->guard_name }}</td>
                                        <td class="text-secondary f-w-600">{{ $role->updated_at->format('F j, Y') }}</td>
                                        <td>{{ $role->created_at->format('F j, Y') }}</td>
                                        <td>
                                            <a class="btn btn-danger icon-btn b-r-4" href="{{ route('roles.confirm', $role) }}">
                                                <i class="ti ti-trash"></i>
                                            </a>
                                            <a class="btn btn-success icon-btn b-r-4" href="{{ route('roles.edit', $role) }}">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
                {{ $roles->links() }}
            </div>
        </div>

    </div>
</main>

@include('_inc._footer')
