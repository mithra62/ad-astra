<header class="header-main">
    <div class="container-fluid">
        <div class="row">

<div class="col-6 col-sm-6 d-flex align-items-center header-left p-0">
               <span class="header-toggle ">
                 <i class="ph ph-squares-four"></i>
               </span>
    {{--
        <div class="header-searchbar w-100">
            <form action="#" class="mx-3 app-form app-icon-form ">
                <div class="position-relative">
                    <input aria-label="Search" class="form-control" placeholder="Search..."
                           type="search">
                    <i class="ti ti-search text-dark"></i>
                </div>
            </form>
        </div>
        --}}
    </div>
                {{--
                <div class="col-6 col-sm-6 d-flex align-items-center justify-content-end header-right p-0">

                    <ul class="d-flex align-items-center">

                        <li class="header-language">
                            <div class="flex-shrink-0 dropdown" id="lang_selector">
                                <a aria-expanded="false" class="d-block head-icon ps-0"
                                   data-bs-toggle="dropdown"
                                   href="#">
                                    <div class="lang-flag lang-en ">
                                                    <span class="flag rounded-circle overflow-hidden">
                                                        <i class=""></i>
                                                    </span>
                                    </div>
                                </a>
                                <ul class="dropdown-menu language-dropdown header-card border-0">
                                    <li class="lang lang-en selected dropdown-item p-2" data-bs-placement="top"
                                        data-bs-toggle="tooltip" title="US">
                                                    <span class="d-flex align-items-center">
                                                        <i class="flag-icon flag-icon-usa flag-icon-squared rounded-circle f-s-20"></i>
                                                        <span class="ps-2">US</span>
                                                    </span>
                                    </li>
                                    <li class="lang lang-pt dropdown-item p-2" title="FR">
                                                    <span class="d-flex align-items-center">
                                                        <i class="flag-icon flag-icon-fra flag-icon-squared rounded-circle f-s-20"></i>
                                                        <span class="ps-2">France</span>
                                                    </span>
                                    </li>
                                    <li class="lang lang-es dropdown-item p-2" title="UK">
                                                    <span class="d-flex align-items-center">
                                                        <i class="flag-icon flag-icon-gbr flag-icon-squared rounded-circle f-s-20"></i>
                                                        <span class="ps-2">UK</span>
                                                    </span>
                                    </li>
                                    <li class="lang lang-es dropdown-item p-2" title="IT">
                                                    <span class="d-flex align-items-center">
                                                        <i class="flag-icon flag-icon-ita flag-icon-squared rounded-circle f-s-20"></i>
                                                        <span class="ps-2">Italy</span>
                                                    </span>
                                    </li>
                                </ul>
                            </div>

                        </li>

                        <li class="header-apps">
                            <a aria-controls="appscanvasRights"
                               class="d-block head-icon bg-light-dark rounded-circle f-s-22 p-2"
                               data-bs-target="#appscanvasRights" data-bs-toggle="offcanvas"
                               href="#" role="button">
                                <i class="ph ph-bounding-box"></i>

                            </a>

                            <div aria-labelledby="appscanvasRightsLabel"
                                 class="offcanvas offcanvas-end header-apps-canvas"
                                 id="appscanvasRights"
                                 tabindex="-1">
                                <div class="offcanvas-header">
                                    <h5 class="offcanvas-title" id="appscanvasRightsLabel">Shortcut</h5>
                                    <div class="app-dropdown flex-shrink-0">
                                        <a aria-expanded="false" class=" p-1" data-bs-auto-close="outside"
                                           data-bs-toggle="dropdown"
                                           href="#"
                                           role="button">
                                            <i class="ph-bold  ph-faders-horizontal f-s-20"></i>


                                        </a>
                                        <ul class="dropdown-menu mb-3 p-2">
                                            <li class="dropdown-item">
                                                <a href="setting.html" target="_blank">
                                                    Privacy Settings
                                                </a>
                                            </li>
                                            <li class="dropdown-item">
                                                <a href="setting.html" target="_blank">
                                                    Account Settings
                                                </a>
                                            </li>
                                            <li class="dropdown-item">
                                                <a href="setting.html" target="_blank">
                                                    Accessibility
                                                </a>
                                            </li>
                                            <li class="dropdown-divider"></li>
                                            <li class="dropdown-item border-0">
                                                <a aria-expanded="false" data-bs-toggle="dropdown" href="#"
                                                   role="button">
                                                    More Settings
                                                </a>
                                                <ul class="dropdown-menu sub-menu">
                                                    <li class="dropdown-item">
                                                        <a href="setting.html" target="_blank">
                                                            Backup and Restore
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-item">
                                                        <a href="setting.html" target="_blank">
                                                            <span>Data Usage</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-item">
                                                        <a href="setting.html" target="_blank">
                                                            <span>Theme</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-item d-flex align-items-center justify-content-between">
                                                        <a href="#">
                                                            <p class="mb-0">Notification</p>
                                                        </a>
                                                        <div class="flex-shrink-0">
                                                            <div class="form-check form-switch">
                                                                <input
                                                                    class="form-check-input  form-check-primary"
                                                                    id="notificationSwitch"
                                                                    type="checkbox">
                                                            </div>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </li>

                                        </ul>
                                    </div>
                                </div>
                                <div class="offcanvas-body app-scroll">
                                    <div class="row row-cols-3 g-2">
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-primary w-100 rounded-3 py-3 px-2 "
                                               href="product.html"
                                               target="_blank">
                                                              <span>
                                                                <i class="ph-light  ph-shopping-bag-open  f-s-30"></i>
                                                              </span>
                                                <p class="mb-0 f-w-500 text-dark">E-shop</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-danger w-100 rounded-3 py-3 px-2 "
                                               href="email.html"
                                               target="_blank">
                                                             <span>
                                                               <i class="ph-light  ph-envelope  f-s-30"></i>
                                                             </span>
                                                <p class="mb-0 f-w-500 text-dark">Email</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-success w-100 rounded-3 py-3 px-2 "
                                               href="chat.html"
                                               target="_blank">
                                                             <span>
                                                               <i class="ph-light  ph-chat-circle-text  f-s-30"></i>
                                                             </span>
                                                <p class="mb-0 f-w-500 text-dark">Chat</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-warning w-100 rounded-3 py-3 px-2 "
                                               href="project_app.html"
                                               target="_blank">
                                                             <span>
                                                               <i class="ph-light  ph-projector-screen-chart  f-s-30"></i>
                                                             </span>
                                                <p class="mb-0 f-w-500 text-dark">Project</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-info w-100 rounded-3 py-3 px-2 "
                                               href="invoice.html"
                                               target="_blank">
                                                             <span>
                                                               <i class="ph-light  ph-scroll f-s-30"></i>
                                                             </span>
                                                <p class="mb-0 f-w-500 text-dark">Invoice</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-dark w-100 rounded-3 py-3 px-2 "
                                               href="blog.html"
                                               target="_blank">
                                                             <span>
                                                               <i class="ph-light  ph-notebook f-s-30"></i>
                                                             </span>
                                                <p class="mb-0 f-w-500 text-dark">Blog</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-danger w-100 rounded-3 py-3 px-2 "
                                               href="calendar.html"
                                               target="_blank">
                                                             <span>
                                                               <i class="ph-light  ph-calendar f-s-30"></i>
                                                             </span>
                                                <p class="mb-0 f-w-500 text-dark">Calender</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-warning w-100 rounded-3 py-3 px-2 "
                                               href="file_manager.html"
                                               target="_blank">
                                                            <span>
                                                              <i class="ph-light  ph-folder-open f-s-30"></i>
                                                            </span>
                                                <p class="mb-0 f-w-500 text-dark txt-ellipsis-1">File
                                                    Manager</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-primary w-100 rounded-3 py-3 px-2 "
                                               href="gallery.html "
                                               target="_blank">
                                                            <span>
                                                              <i class="ph-light  ph-google-photos-logo f-s-30"></i>
                                                            </span>
                                                <p class="mb-0 f-w-500 text-dark">Gallery</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-success w-100 rounded-3 py-3 px-2 "
                                               href="profile.html"
                                               target="_blank">
                                                            <span>
                                                              <i class="ph-light  ph-users-three f-s-30"></i>
                                                            </span>
                                                <p class="mb-0 f-w-500 text-dark">Profile</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="text-light-secondary w-100 rounded-3 py-3 px-2 "
                                               href="kanban_board.html"
                                               target="_blank">
                                                            <span>
                                                              <i class="ph-light  ph-selection-foreground f-s-30"></i>
                                                            </span>
                                                <p class="mb-0 f-w-500 text-dark">Task Board</p>
                                            </a>
                                        </div>
                                        <div class="d-flex-center text-center">
                                            <a class="d-flex-center text-light-secondary w-100 h-100 rounded-3 p-2 dashed-1-secondary"
                                               href="kanban_board.html"
                                               target="_blank">
                                                            <span>
                                                              <i class="ph-light  ph-plus f-s-30"></i>
                                                            </span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="header-cart">
                            <a aria-controls="cartcanvasRight"
                               class="d-block head-icon position-relative bg-light-dark rounded-circle f-s-22 p-2"
                               data-bs-target="#cartcanvasRight"
                               data-bs-toggle="offcanvas"
                               href="#" role="button">
                                <i class="ph ph-shopping-cart-simple"></i>
                                <span
                                    class="position-absolute translate-middle badge rounded-pill bg-danger badge-notification">4</span>
                            </a>
                            <div aria-labelledby="cartcanvasRightLabel"
                                 class="offcanvas offcanvas-end header-cart-canvas"
                                 id="cartcanvasRight"
                                 tabindex="-1">
                                <div class="offcanvas-header">
                                    <h5 class="offcanvas-title" id="cartcanvasRightLabel">Cart</h5>
                                    <button aria-label="Close" class="btn-close" data-bs-dismiss="offcanvas"
                                            type="button"></button>
                                </div>
                                <div class="offcanvas-body app-scroll p-0">
                                    <div class="head-container">
                                        <div class="head-box">
                                                      <span
                                                          class="b-1-light bg-light-primary h-45 w-45 d-flex-center b-r-6">
                                                          <img alt="cart" class="img-fluid p-1"
                                                               src="../assets/images/header/cart/01.png">
                                                      </span>

                                            <div class="flex-grow-1 ms-2">
                                                <a class="mb-0 f-w-600 f-s-16" href="product_details.html"
                                                   target="_blank"> Backpacks (3<i
                                                        class="ti ti-star-filled text-warning f-s-12"></i>)
                                                </a>
                                                <div>
                                                                <span class="text-dark"><span
                                                                        class="text-secondary f-w-400">size</span> : M</span>
                                                    <span class="text-dark ms-2"><span
                                                            class="text-secondary f-w-400">color</span> :Pink</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <p class="text-muted f-w-500 mb-0">$600.50 x 1</p>
                                            </div>
                                        </div>
                                        <div class="head-box">
                                                        <span
                                                            class="b-1-light bg-light-primary h-45 w-45 d-flex-center b-r-6">
                                                          <img alt="cart" class="img-fluid p-1"
                                                               src="../assets/images/header/cart/05.png">
                                                      </span>
                                            <div class="flex-grow-1 ms-2">
                                                <a class="mb-0 f-w-600 f-s-16" href="product_details.html"
                                                   target="_blank"> Women's Watch (4<i
                                                        class="ti ti-star-filled text-warning f-s-12"></i>)</a>
                                                <div>
                                                                <span class="text-dark"><span
                                                                        class="text-secondary f-w-400">size</span> : S</span>
                                                    <span class="text-dark ms-2"><span
                                                            class="text-secondary f-w-400">color</span> :Rose Gold</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <p class="text-muted f-w-500 mb-0">$519.10 x 2</p>
                                            </div>
                                        </div>
                                        <div class="head-box">
                                                        <span
                                                            class="b-1-light bg-light-primary h-45 w-45 d-flex-center b-r-6">
                                                          <img alt="cart" class="img-fluid p-1"
                                                               src="../assets/images/header/cart/04.png">
                                                      </span>
                                            <div class="flex-grow-1 ms-2">
                                                <a class="mb-0 f-w-600 f-s-16" href="product_details.html"
                                                   target="_blank">Sandals (5 <i
                                                        class="ti ti-star-filled text-warning f-s-12"></i>)</a>
                                                <div>
                                                                <span class="text-dark"><span
                                                                        class="text-secondary f-w-400">size</span> : 8</span>
                                                    <span class="text-dark ms-2"><span
                                                            class="text-secondary f-w-400">color</span> :White</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <p class="text-muted f-w-500 mb-0">$390 x 2</p>
                                            </div>
                                        </div>
                                        <div class="head-box ">
                                                        <span
                                                            class="b-1-light bg-light-primary h-45 w-45 d-flex-center b-r-6">
                                                          <img alt="cart" class="img-fluid p-1"
                                                               src="../assets/images/header/cart/03.png">
                                                      </span>
                                            <div class="flex-grow-1 ms-2">
                                                <a class="mb-0 f-w-600 f-s-16" href="product_details.html"
                                                   target="_blank"> Jackets (3<i
                                                        class="ti ti-star-filled text-warning f-s-12"></i>)</a>
                                                <div>
                                                                <span class="text-dark"><span
                                                                        class="text-secondary f-w-400">size</span> : XL</span>
                                                    <span class="text-dark ms-2"><span
                                                            class="text-secondary f-w-400">color</span> :Blue</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <p class="text-muted f-w-500 mb-0">$300.00 x 2</p>
                                            </div>
                                        </div>
                                        <div class="head-box ">
                                                        <span
                                                            class="b-1-light bg-light-primary h-45 w-45 d-flex-center b-r-6">
                                                          <img alt="cart" class="img-fluid p-1"
                                                               src="../assets/images/header/cart/02.png">
                                                      </span>
                                            <div class="flex-grow-1 ms-2">
                                                <a class="mb-0 f-w-600 f-s-16" href="product_details.html"
                                                   target="_blank"> Shoes (3<i
                                                        class="ti ti-star-filled text-warning f-s-12"></i>)</a>
                                                <div>
                                                                <span class="text-dark"><span
                                                                        class="text-secondary f-w-400">size</span> : 9</span>
                                                    <span class="text-dark ms-2"><span
                                                            class="text-secondary f-w-400">color</span> :White</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <p class="text-muted f-w-500 mb-0">$450.00 x 1</p>
                                            </div>
                                        </div>
                                        <div class="hidden-massage py-4 px-3">

                                            <div>
                                                <i class="ph-duotone  ph-shopping-bag-open f-s-50 text-primary"></i>
                                                <h6 class="mb-0">Your Cart is Empty</h6>
                                                <p class="text-secondary mb-0">Add some items :)</p>
                                                <a class="btn btn-light-primary btn-xs mt-2"
                                                   href="product_details.html">Shop
                                                    Now</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="offcanvas-footer">
                                    <div class="head-box-footer p-3">
                                        <div class="mb-4">
                                            <h6 class="text-muted f-w-600">Total <span
                                                    class="float-end text-primary">$3,468.00</span></h6>
                                        </div>
                                        <div class="header-cart-btn">
                                            <a class="btn btn-primary" href="./cart.html" role="button"
                                               target="_blank">
                                                <i class="ti ti-eye"></i> View Cart</a>
                                            <a class="btn btn-success" href="./checkout.html" role="button"
                                               target="_blank">
                                                Checkout <i class="ti ti-shopping-cart"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="header-dark">
                            <div class="sun-logo head-icon bg-light-dark rounded-circle f-s-22 p-2">
                                <i class="ph ph-moon-stars"></i>
                            </div>
                            <div class="moon-logo head-icon bg-light-dark rounded-circle f-s-22 p-2">
                                <i class="ph ph-sun-dim"></i>
                            </div>
                        </li>

                        <li class="header-notification">
                            <a aria-controls="notificationcanvasRight"
                               class="d-block head-icon position-relative bg-light-dark rounded-circle f-s-22 p-2"
                               data-bs-target="#notificationcanvasRight"
                               data-bs-toggle="offcanvas"
                               href="#"
                               role="button">
                                <i class="ph ph-bell"></i>
                                <span
                                    class="position-absolute translate-middle p-1 bg-primary border border-light rounded-circle animate__animated animate__fadeIn animate__infinite animate__slower"></span>
                            </a>
                            <div aria-labelledby="notificationcanvasRightLabel"
                                 class="offcanvas offcanvas-end header-notification-canvas"
                                 id="notificationcanvasRight" tabindex="-1">
                                <div class="offcanvas-header">
                                    <h5 class="offcanvas-title" id="notificationcanvasRightLabel">
                                        Notification</h5>
                                    <button aria-label="Close" class="btn-close" data-bs-dismiss="offcanvas"
                                            type="button"></button>
                                </div>
                                <div class="offcanvas-body app-scroll p-0">
                                    <div class="head-container">
                                        <div class="notification-message head-box">

                                            <div class="message-content-box flex-grow-1 pe-2">

                                                <a class="f-s-15 text-dark mb-0"
                                                   href="./read_email.html"><span
                                                        class="f-w-500 text-dark">Gene Hart</span> wants to
                                                    edit <span
                                                        class="f-w-500 text-dark">Report.doc</span></a>
                                                <div>
                                                    <a class="d-inline-block f-w-500 text-success me-1"
                                                       href="#">Approve</a>
                                                    <a class="d-inline-block f-w-500 text-danger"
                                                       href="#">Deny</a>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <div>
                                                    <span class="badge text-light-primary"> sep 23 </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="notification-message head-box">

                                            <div class="message-content-box flex-grow-1 pe-2">
                                                <a class="f-s-15 text-dark mb-0" href="./read_email.html">Hey
                                                    <span
                                                        class="f-w-500 text-dark">Emery McKenzie</span>,
                                                    get ready: Your order from <span
                                                        class="f-w-500 text-dark">@Shopper.com</span></a>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <div>
                                                    <span class="badge text-light-primary"> sep 23 </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="notification-message head-box">
                                            <div class="message-content-box flex-grow-1 pe-2">
                                                <a class="f-s-15 text-dark mb-0"
                                                   href="./read_email.html"><span
                                                        class="f-w-500 text-dark">Simon Young</span> shared
                                                    a file called <span
                                                        class="f-w-500 text-dark">Dropdown.pdf</span></a>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <div>
                                                    <span class="badge text-light-primary"> 30 min</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="notification-message head-box">
                                            <div class="message-content-box flex-grow-1 pe-2">
                                                <a class="f-s-15 text-dark mb-0"
                                                   href="./read_email.html"><span
                                                        class="f-w-500 text-dark">Becky G. Hayes</span> has
                                                    added a comment to <span
                                                        class="f-w-500 text-dark">Final_Report.pdf</span></a>
                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <div>
                                                    <span class="badge text-light-primary"> 45 min</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="notification-message head-box">
                                            <div class="message-content-box  flex-grow-1 pe-2">
                                                <a class="f-s-15 text-dark mb-0 "
                                                   href="./read_email.html"><span
                                                        class="f-w-600 text-dark">@Romaine</span>
                                                    invited you to a meeting
                                                </a>
                                                <div>
                                                    <a class="d-inline-block f-w-500 text-success me-1"
                                                       href="#">Join</a>
                                                    <a class="d-inline-block f-w-500 text-danger" href="#">Decline</a>
                                                </div>

                                            </div>
                                            <div class="text-end">
                                                <i class="ph ph-trash f-s-18 text-danger close-btn"></i>
                                                <div>
                                                    <span class="badge text-light-primary"> 1 hour ago </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="hidden-massage py-4 px-3">
                                            <div>
                                                <i class="ph-duotone  ph-bell-ringing f-s-50 text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Notification Not Found</h6>
                                                <p class="text-dark">When you have any notifications added
                                                    here,will
                                                    appear here.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </li>
                    </ul>
                </div>
                --}}
        </div>
    </div>
</header>
