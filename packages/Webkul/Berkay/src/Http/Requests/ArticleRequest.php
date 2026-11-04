<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
{
    /** Require the permission corresponding to the requested editorial action. */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('berkay.articles.'.($this->isMethod('post') ? 'create' : 'edit'));
    }

    /** Validate editorial fields and prevent duplicate article addresses. */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u', Rule::unique('berkay_articles', 'slug')->ignore($this->route('id'))],
            'category' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:600'],
            'body' => ['required', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** Fill optional publishing metadata from the article itself. */
    protected function prepareForValidation(): void
    {
        $body = strip_tags((string) $this->input('body'));
        $this->merge([
            'slug' => $this->input('slug') ?: 'article-'.strtolower((string) Str::ulid()),
            'meta_title' => $this->input('meta_title') ?: $this->input('title'),
            'meta_description' => $this->input('meta_description') ?: Str::limit($body, 155, ''),
            'excerpt' => $this->input('excerpt') ?: Str::limit($body, 250, ''),
            'status' => $this->input('status') ?: 'draft',
        ]);
    }
}
