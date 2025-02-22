<div class="card">
    <div class="card-header">{{ __('settings.title-profile') }}</div>

    <div class="card-body">
        <form enctype="multipart/form-data" method="POST" action="{{ route('settings') }}">
            @csrf
            <div class="form-group row">
                <label for="name" class="col-md-4 col-form-label text-md-right">
                    {{ __('settings.picture') }}
                </label>
                <div class="col-md-6 text-center">
                    <div class="image-box">
                        <img
                                src="{{ \App\Http\Controllers\Backend\User\ProfilePictureController::getUrl(auth()->user()) }}"
                                style="max-width: 96px" alt="{{__('settings.picture')}}" class="pb-2"
                                id="theProfilePicture"
                        />
                    </div>

                    <a href="#" class="btn btn-primary mb-3" data-mdb-toggle="modal"
                       data-mdb-target="#uploadAvatarModal">
                        {{__('settings.upload-image')}}
                    </a>

                    @isset(auth()->user()->avatar)
                        <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm mb-3"
                           data-mdb-toggle="modal"
                           data-mdb-target="#deleteProfilePictureModal"
                        >{{ __('settings.delete-profile-picture-btn') }}</a>
                    @endisset

                    @error('avatar')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-md-4 col-form-label text-md-right">
                    {{ __('user.username') }}
                </label>

                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input id="username" type="text"
                               class="form-control @error('username') is-invalid @enderror"
                               name="username" value="{{ auth()->user()->username }}" required autofocus/>
                    </div>

                    @error('username')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>

            <div class="form-group row">
                <label for="name" class="col-md-4 col-form-label text-md-right">
                    {{ __('user.displayname') }}
                </label>
                <div class="col-md-6">
                    <input id="name" type="text"
                           class="form-control @error('name') is-invalid @enderror" name="name"
                           value="{{ auth()->user()->name }}" required autocomplete="name"/>

                    @error('name')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>

            <div class="form-group row">
                <label for="email" class="col-md-4 col-form-label text-md-right">
                    {{ __('user.email') }}
                </label>
                <div class="col-md-6">
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror" name="email"
                           value="{{ auth()->user()->email }}" autocomplete="email"/>

                    @error('email')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>

            <div class="form-group row">
                <div class="col-md-6 offset-md-4">
                    <div class="custom-control custom-checkbox custom-control-inline">
                        <input id="always_dbl" type="checkbox"
                               class="custom-control-input @error('always_dbl') is-invalid @enderror"
                               name="always_dbl" {{ auth()->user()->always_dbl ? 'checked' : '' }} />
                        <label class="custom-control-label" for="always_dbl">
                            {{ __('user.always-dbl') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group row mb-0">
                <div class="col-md-6 offset-md-4">
                    <button type="submit" class="btn btn-primary">
                        {{ __('settings.btn-update') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="uploadAvatarModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="mb-0">{{__('settings.upload-image')}}</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>
                    <strong>{{__('settings.choose-file')}}: </strong>
                    <input type="file" id="image">
                </p>

                <div class="d-none text-trwl text-center" id="upload-error" role="alert">
                    {{ __('settings.something-wrong') }}
                </div>

                <div id="upload-demo" class="d-none"></div>
                <button class="btn btn-primary btn-block upload-image d-none" id="upload-button">
                    {{__('settings.upload-image')}}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProfilePictureModal" tabindex="-1" role="dialog"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="mb-0">{{__('settings.delete-profile-picture')}}:</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{!! __('settings.delete-profile-picture-desc') !!}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-danger" data-mdb-dismiss="modal"
                        aria-label="{{ __('settings.delete-profile-picture-no') }}">
                    {{ __('settings.delete-profile-picture-no') }}
                </button>
                <a href="{{ route('settings.delete-profile-picture') }}" class="btn btn-danger">
                    {{ __('settings.delete-profile-picture-yes') }}
                </a>
            </div>
        </div>
    </div>
</div>
