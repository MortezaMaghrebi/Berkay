<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Webkul\Admin\Http\Controllers\Controller;

class ArticleImportController extends Controller
{
    /** Validate and sanitize assistant output before it reaches the browser editor. */
    public function parse(Request $request): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.articles.create'), 403);
        $input = $request->validate(['payload' => ['required', 'string', 'max:120000']]);
        $raw = preg_replace('/^`{3}(?:json)?\s*|\s*`{3}$/i', '', trim($input['payload']));
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['payload' => trans('berkay::app.ai.invalid')]);
        }
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['payload' => trans('berkay::app.ai.invalid')]);
        }
        $data = Validator::make($decoded, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u'],
            'category' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['required', 'string', 'max:600'],
            'body' => ['required', 'string', 'max:100000'],
            'meta_title' => ['required', 'string', 'max:255'],
            'meta_description' => ['required', 'string', 'max:320'],
        ])->validate();
        $data['body'] = clean_content(strip_tags($data['body'], '<p><h2><h3><h4><ul><ol><li><strong><b><em><i><blockquote><a><table><thead><tbody><tr><th><td><br>'));

        return response()->json(['data' => $data]);
    }
}
