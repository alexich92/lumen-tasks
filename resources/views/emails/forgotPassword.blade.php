<h1>Hi {{ $user->name }}</h1>
<p>Please use the following code to reset your password:</p>
<br/>
<p style="font-weight: bold">{{ $user->forgot_code}}</p>
<br />
<p>Have a good day!</p>