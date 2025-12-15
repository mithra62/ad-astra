@include('_inc._header', ['title' => 'Account Settings'])

<main>
    <div class="container-fluid">
        <!-- Breadcrumb start -->
        <div class="row m-1">
            <div class="col-12 ">
                <h4 class="main-title">Setting</h4>
                <ul class="app-line-breadcrumbs mb-3">
                    <li class="">
                        <a class="f-s-14 f-w-500" href="{{ route('dashboard') }}">
                            <span>
                                <i class="ph-duotone  ph-table f-s-16"></i> Dashboard
                            </span>
                        </a>
                    </li>
                    <li class="active">
                        <a class="f-s-14 f-w-500" href="{{ route('account.settings') }}">Account Settings</a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Breadcrumb end -->
        @include('_inc._message')
        <!-- setting-app start -->
        <div class="row">
            @include('account._sidebar', ['active' => 'settings'])
            <div class="col-lg-8 col-xxl-9">
                <div class="tab-content">
                    <div aria-labelledby="profile-tab" class="tab-pane fade active show"
                         id="profile-tab-pane"
                         role="tabpanel" tabindex="0">
                        <div class="card setting-profile-tab">
                            <div class="card-header">
                                <h5>Profile</h5>
                            </div>
                            <div class="card-body">
                                <div class="profile-tab profile-container">
                                    <form method="POST" action="{{ route('account.edit') }}"
                                          class="row g-3 needs-validation"
                                          novalidate>
                                        @csrf
                                        {{ method_field('PUT') }}
                                        <h5 class="mb-2 text-dark f-w-600">User Info</h5>
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Name</label>
                                                    <input class="form-control" placeholder="" name="name" type="text"
                                                           value="{{ old('name', Auth::user()->name) }}">
                                                </div>
                                                @error('name')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Email address</label>
                                                    <input class="form-control" name="email" placeholder="" type="email"
                                                           value="{{ old('email', Auth::user()->email) }}">
                                                </div>
                                                @error('email')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Phone Number</label>
                                                    <input class="form-control" name="phone" placeholder="" type="text"
                                                           value="{{ old('phone', Auth::user()->phone) }}">
                                                </div>
                                                @error('phone')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input class="form-control" name="title" placeholder="" type="text"
                                                           value="{{ old('title', Auth::user()->title) }}">
                                                </div>
                                                @error('title')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-12">
                                                <div class="text-end">
                                                    <button class="btn btn-primary" type="submit">Submit</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @can('api')
                    <div aria-labelledby="tokens-tab" class="tab-pane fade" id="tokens-tab-pane"
                         role="tabpanel" tabindex="0">
                        <div class="card tokens-card-content">
                            <div class="card-body">
                                <div class="account-security">
                                    <div class="row align-items-center">
                                        <div class="col-sm-8">
                                            <h5 class="text-primary f-w-600">Account Security</h5>
                                            <p class=" account-discription text-secondary f-s-16 mt-2 mb-0">
                                                your account is valuable to
                                                hackers. to make 2-step verification very secure, use
                                                your phone's built-in security key</p>
                                        </div>
                                        <div class="col-sm-4 account-security-img">
                                            <img alt="" class="w-180"
                                                 src="../assets/images/setting-app/account.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="row security-box-card align-items-center">
                                    <div class="col-md-3 position-relative">
<span><img alt="" class="w-35 h-35 anti-code"
           src="../assets/images/setting-app/google.png"></span>
                                        <p
                                            class="security-box-title text-dark f-w-500 f-s-16 ms-5 security-code">
                                            Authentication</p>
                                    </div>
                                    <div class="col-md-6 security-discription">
                                        <p class=" text-secondary f-s-16">It encompasses various methods
                                            to ensure that the person requesting access is indeed who
                                            they claim to be. Here are the key components and features
                                            of Google Authentication:
                                        </p>
                                        <span class="badge text-light-secondary p-2"> <i
                                                class="ph-fill  ph-check-circle me-1 text-success"></i>secondary</span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <button class="btn btn-outline-success" type="button">Turn
                                            off
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="row security-box-card align-items-center">
                                    <div class="col-md-3 position-relative">
<span
    class="bg-primary h-35 w-35 d-flex-center b-r-50 anti-code">
<i class="ph-light  ph-codepen-logo f-s-18"></i></span>
                                        <p
                                            class="security-box-title text-dark f-w-500 f-s-16 ms-5 security-code">
                                            Anti-
                                            Code</p>
                                    </div>
                                    <div class="col-md-6 security-discription">
                                        <p class="text-secondary f-s-16">An anti-phishing code is a
                                            security feature used by various online platforms,<br>
                                            especially in financial and cryptocurrency services,
                                        </p>
                                        <span class="badge text-light-secondary p-2"> <i
                                                class="ph-fill  ph-x-circle me-1 text-primary"></i>secondary</span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <button class="btn btn-primary" type="button">Turn On</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="row security-box-card align-items-center">
                                    <div class="col-md-3 position-relative">
<span
    class="bg-success h-35 w-35 d-flex-center b-r-50 anti-code">
<i class="ph-light  ph-file-archive f-s-18"></i></span>
                                        <p
                                            class="security-box-title text-dark f-w-500 f-s-16 ms-5 security-code">
                                            Whitelist
                                        </p>
                                    </div>
                                    <div class="col-md-6 security-discription">
                                        <p class="text-secondary f-s-16">An anti-phishing code is a
                                            security feature used by various online platforms,<br>
                                            especially in financial and cryptocurrency services,
                                        </p>

                                    </div>
                                    <div class="col-md-3 text-end">
                                        <p>In development</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card security-card-content">
                            <div class="card-body">
                                <div class="account-security">
                                    <div class="row align-items-center">
                                        <div class="col-sm-9">
                                            <h5 class="text-primary f-w-600">Devices and active
                                                sessions</h5>
                                            <p class="account-discription text-secondary f-s-16 mt-3">
                                                your account is valuable to
                                                hackers. to make 2-step verifivcation very secure, use
                                                your phone's built-in security key</p>
                                        </div>
                                        <div class="col-sm-3 account-security-img">
                                            <img alt="" class="w-150"
                                                 src="../assets/images/setting-app/device.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 col-xxl-6">
                                <ul class="active-device-session active-device-list" id="shareMenuLeft">
                                    <li>
                                        <div class="card share-menu-active">
                                            <div class="card-body">
                                                <div class="device-menu-item" draggable="false">
<span class="device-menu-img">
<i class="ph-duotone  ph-laptop f-s-40 text-success"></i>
</span>
                                                    <div class="device-menu-content">
                                                        <h6 class="mb-0 txt-ellipsis-1">Apple Mac
                                                            10.15.7</h6>
                                                        <p class="mb-0 txt-ellipsis-1 text-secondary">
                                                            switzerland 201.36.24.108</p>
                                                    </div>
                                                    <div class="device-menu-icons">

<span
    class="badge text-light-secondary p-2 f-s-16">
<i
    class="ph-fill  ph-check-circle me-1 text-success"></i>Online</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="device-menu-item " draggable="false">
                                                    <span class="device-menu-img">
                                                        <i class="ph-duotone  ph-device-mobile f-s-40 text-primary"></i>
                                                    </span>
                                                    <div class="device-menu-content">
                                                        <h6 class="mb-0 txt-ellipsis-1">Apple Iphone ios
                                                            15.0.2</h6>
                                                        <p class="mb-0 txt-ellipsis-1 text-secondary">
                                                            Ukraine
                                                            176.38.19.14</p>
                                                    </div>
                                                    <div class="device-menu-icons">

                                                        <span class="badge text-light-secondary p-2 f-s-16">
                                                            <i class="ph-fill  ph-x-circle me-1 text-primary"></i>
                                                            Offline
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="device-menu-item " draggable="false">
                                                    <span class="device-menu-img">
                                                        <i class="ph-duotone  ph-device-mobile f-s-40 text-primary"></i>
                                                    </span>
                                                    <div class="device-menu-content">
                                                        <h6 class="mb-0 txt-ellipsis-1">Apple Iphone ios
                                                            15.0.2</h6>
                                                        <p class="mb-0 txt-ellipsis-1 text-secondary">Africa
                                                            176.49.19.13</p>
                                                    </div>
                                                    <div class="device-menu-icons">
                                                        <span class="badge text-light-secondary p-2 f-s-16">
                                                            <i class="ph-fill  ph-x-circle me-1 text-primary"></i>
                                                            Offline
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>

                                </ul>
                            </div>
                            <div class="col-lg-12 col-xxl-6">
                                <ul class="active-device-session  active-device-list"
                                    id="shareMenuRight">
                                    <li>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="device-menu-item " draggable="false">
                                                    <span class="device-menu-img">
                                                    <i class="ph-duotone  ph-device-mobile f-s-40 text-primary"></i>
                                                    </span>
                                                    <div class="device-menu-content">
                                                        <h6 class="mb-0 txt-ellipsis-1">Apple Mac
                                                            10.15.7</h6>
                                                        <p class="mb-0 txt-ellipsis-1 text-secondary">
                                                            America 201.136.24.108</p>
                                                    </div>
                                                    <div class="device-menu-icons">

                                                    <span class="badge text-light-secondary p-2 f-s-16">
                                                    <i class="ph-fill  ph-x-circle me-1 text-primary"></i>Offline</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="device-menu-item " draggable="false">
                                                    <span class="device-menu-img">
                                                    <i class="ph-duotone  ph-device-mobile f-s-40 text-primary"></i>
                                                    </span>
                                                    <div class="device-menu-content">
                                                        <h6 class="mb-0">Windows 10</h6>
                                                        <p class="mb-0 text-secondary">
                                                            portuguese 176.38.19.14</p>
                                                    </div>
                                                    <div class="device-menu-icons">

                                                    <span class="badge text-light-secondary p-2 f-s-16">
                                                    <i class="ph-fill  ph-x-circle me-1 text-primary"></i>Offline</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    @endcan
                    <div aria-labelledby="password-tab" class="tab-pane fade" id="password-tab-pane"
                         role="tabpanel" tabindex="0">
                        <div class="card equal-card password-card">
                            <div class="card-header">
                                <h5>Password </h5>
                            </div>
                            <div class="card security-card-content">
                                <div class="card-body">
                                    <form method="POST" action="{{ route('account.password.update') }}"
                                          class="row g-3 needs-validation"
                                          novalidate>
                                        @csrf
                                        {{ method_field('PUT') }}
                                    <div class="account-security mb-2">
                                        <div class="row align-items-center">
                                            <div class="col-sm-12">
                                                <h5 class="text-primary f-w-600">Change Password</h5>
                                                <p class="account-discription text-secondary f-s-16 mt-3">
                                                    To change your password, please fill in the fields below.
                                                    your password must
                                                    contain at least 8 character, it must also include at
                                                    least one upper case letter, one lower case letter, one
                                                    number and one special character.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="app-form">
                                        <div class="row">
                                            <div class="col-sm-12">
                                                <label class="form-label" for="password">Current Password</label>
                                                <div class="input-group input-group-password mb-3">
                                                    <span class="input-group-text b-r-left">
                                                        <i class="ph-bold  ph-lock f-s-20"></i>
                                                    </span>
                                                    <input name="current_password" id="current_password" class="form-control" type="password">
                                                    <!-- <span class="input-group-text b-r-right"><i
                                                    class="ph ph-eye-slash f-s-20 eyes-icon1" onclick="togglePasswordVisibility()"></i></span> -->
                                                    <span class="input-group-text b-r-right">
                                                        <i class="ph ph-eye-slash f-s-20 eyes-icon " id="showPassword"></i>
                                                    </span>
                                                </div>
                                                @error('current_password')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-sm-12">
                                                <label class="form-label" for="password">New Password</label>
                                                <div class="input-group input-group-password mb-3">
                                                    <span class="input-group-text b-r-left">
                                                        <i class="ph-bold ph-lock f-s-20"></i>
                                                    </span>
                                                    <input class="form-control" name="password" id="password" placeholder="" type="password">
                                                    <span class="input-group-text b-r-right">
                                                        <i class="ph ph-eye-slash f-s-20 eyes-icon1 " id="showPassword1"></i>
                                                    </span>
                                                </div>
                                                @error('password')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-sm-12">
                                                <label class="form-label" for="password">Confirm Password</label>
                                                <div class="input-group input-group-password mb-3">
                                                    <span class="input-group-text b-r-left">
                                                        <i class="ph-bold  ph-lock f-s-20"></i>
                                                    </span>
                                                    <input class="form-control" name="password_confirmation" id="password_confirmation" placeholder="" type="password">
                                                    <span class="input-group-text b-r-right">
                                                        <i class="ph ph-eye-slash f-s-20 eyes-icon2" id="showPassword2"></i>
                                                    </span>
                                                </div>
                                                @error('password_confirmation')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="col-12">
                                                <div class="text-end">
                                                    <button class="btn btn-primary" type="submit">Submit</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--setting app end -->
    </div>
</main>
@include('_inc._footer', ['status' => 'complete'])
