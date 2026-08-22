<?php

namespace Ayvazyan10\Imagic;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Tool;

class MediaLibraryTool extends Tool
{
    public function menu(Request $request): mixed
    {
        return MenuItem::link(
            (string) config('imagic.media_library.menu_label', 'Media Library'),
            '/'.trim((string) config('imagic.media_library.page_path', 'imagic-media'), '/'),
        )->canSee(function (Request $request): bool {
            if (! (bool) config('imagic.media_library.show_in_menu', true)) {
                return false;
            }

            $gate = config('imagic.media_library.authorization_gate');

            return $request->user() !== null
                && (! $gate || Gate::forUser($request->user())->allows($gate));
        });
    }
}
