<article class="post-card">
  <h2 class="post-card-title"><a href="{{ $post->path() }}">{{ $post->title }}</a></h2>
  @if ($summary = $post->summary(220))
  <p class="post-card-summary">{{ $summary }}</p>
  @endif
  <footer class="post-meta">@include('site.partials.post-meta', ['post' => $post])</footer>
</article>
