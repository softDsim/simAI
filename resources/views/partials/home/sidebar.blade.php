<div class="main-sidebar">
        <div class="sidebar-content">
            <div class="upper-panel">
                <button id="chat-sb-btn" onclick="onSidebarButtonDown('chat')" href="chat" class="btn-sm sidebar-btn tooltip-parent">
                    <x-icon name="chat-icon"/>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Chat"] }}
                    </div>
                </button>
                <button id="groupchat-sb-btn" onclick="onSidebarButtonDown('groupchat')" class="btn-sm sidebar-btn tooltip-parent">
                    <x-icon name="assistant-icon"/>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Groupchat"] }}
                    </div>
                </button>

                <button id="profile-sb-btn" onclick="onSidebarButtonDown('profile')" class="btn-sm sidebar-btn tooltip-parent">
                    <div class="profile-icon round-icon">
                        <span class="user-inits" style="display:none"></span>
                        <img class="icon-img"   alt="">
                    </div>
                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Profile"] }}
                    </div>
                </button>
                @if(Auth::user()->employeetype === 'professor')
                <button id="rag-library-sb-btn" onclick="onSidebarButtonDown('rag-library')" class="btn-sm sidebar-btn tooltip-parent">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-database"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                    <div class="label tooltip tt-abs-left">
                        Wissensdatenbank
                    </div>
                </button>
                @endif
                </div>



            <div class="lower-panel">
                <button onclick="logout()" class="btn-sm sidebar-btn tooltip-parent" >
                    <x-icon name="logout-icon"/>
                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Logout"] }}
                    </div>
                </button>
                <button class="btn-sm sidebar-btn tooltip-parent" onclick="toggleSettingsPanel(true)">
                    <x-icon name="settings-icon"/>
                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Settings"] }}
                    </div>
                </button>
            </div>
        </div>
        <!-- <div class="logo-panel">
            <img src="{{ asset('img/logo.svg') }}" alt="">
        </div> -->

	</div>
