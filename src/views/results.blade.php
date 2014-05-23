<h1>Search</h1>

<form class="search-form form-inline" role="form" method="GET" action="{{ url('search') }}">
	<div class="form-group">
		<label class="sr-only" for="query">Search query</label>
		<input type="text" class="form-control" id="query" name="q" value="{{ $query }}">
	</div>
	<button type="submit" class="btn btn-default">Search</button>
</form>

@if ($query)

	@if ($results->count() > 0)
		<h2>Results for "{{ $query }}" ({{ $results->getTotal() }})</h2>

		<table class="table table-striped">
			@foreach ($results as $hit)
				<tr>
					<td>
						@if (isset($hit['_source']['url']))
							<a href="{{ $hit['_source']['url'] }}">{{ $hit['_source']['name'] }}</a>
						@else
							{{ $hit['_source']['name'] }}
						@endif
					</td>
					<td>Score (debug): {{ $hit['_score'] }}</td>
					{{--
					<td>
						<pre>{{ var_export($hit['_source']) }}</pre>
					</td>
					--}}
				</tr>
			@endforeach
		</table>

		{{ $results->appends(['q' => $query])->links() }}
	@else

		<h2>No results for "{{ $query }}"</h2>
		<p>Please try again with a different query.</p>

	@endif

@endif