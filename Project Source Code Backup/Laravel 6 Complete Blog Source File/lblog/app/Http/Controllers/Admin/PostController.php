<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Notifications\AuthorPostApproved;
use App\Notifications\NewPostNotify;
use Illuminate\Support\Facades\Notification;
use App\Subscriber;
use App\Http\Controllers\Controller;
use App\Post;
use App\Category;
use App\Tag; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;


class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       $posts = Post::latest()->get();
       return view('admin.post.index',compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {   
        $categories = Category::all();
        $tags = Tag::all();
        return view('admin.post.create',compact('categories','tags'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
         $this->validate($request,[
          'title' => 'required',
          'image' => 'required',
          'categories' => 'required',
          'tags' => 'required',
          'body' => 'required',

         ]);

   // Get Form Image
        $image = $request->file('image');
        $slug = str_slug($request->title);
        if (isset($image)) {
           
           // Make Unique Name for Image 
          $currentDate = Carbon::now()->toDateString();
  $imageName = $slug.'-'.$currentDate.'-'.uniqid().'.'.$image->getClientOriginalExtension();


        // Check Category Dir is exists

            if (!Storage::disk('public')->exists('post')) {
               Storage::disk('public')->makeDirectory('post');
            }


            // Resize Image for category and upload
            $postImage = Image::make($image)->resize(1600,1066)->stream();
            Storage::disk('public')->put('post/'.$imageName,$postImage);

   }else{
    $imageName = "default.png";
   }

  $post = new Post();
  $post->user_id = Auth::id();
  $post->title = $request->title;
  $post->slug = $slug;
  $post->image = $imageName;
  $post->body = $request->body;
  if (isset($request->status)) {
    $post->status = true;
  }else{
    $post->status = false;
  }
  $post->is_approved = true;
  $post->save();

  $post->categories()->attach($request->categories);
  $post->tags()->attach($request->tags);
   
   $subscribers = Subscriber::all();
   foreach ($subscribers as $subscriber) {
     Notification::route('mail',$subscriber->email)
     ->notify(new NewPostNotify($post));
   }


  return redirect(route('admin.post.index'))->with('successMsg', 'Post Inserted Successfully');




    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function show(Post $post)
    {

       return view('admin.post.show',compact('post'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit(Post $post)
    {
         $categories = Category::all();
        $tags = Tag::all();
        return view('admin.post.edit',compact('post','categories','tags'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Post $post)
    {
        $this->validate($request,[
          'title' => 'required',
          'image' => 'image',
          'categories' => 'required',
          'tags' => 'required',
          'body' => 'required',

         ]);

   // Get Form Image
        $image = $request->file('image');
        $slug = str_slug($request->title);
        if (isset($image)) {
           
           // Make Unique Name for Image 
          $currentDate = Carbon::now()->toDateString();
  $imageName = $slug.'-'.$currentDate.'-'.uniqid().'.'.$image->getClientOriginalExtension();


        // Check Category Dir is exists
            if (!Storage::disk('public')->exists('post')) {
               Storage::disk('public')->makeDirectory('post');
            }

         // Delete old post image
        if(Storage::disk('public')->exists('post/'.$post->image)){
          Storage::disk('public')->delete('post/'.$post->image);

        }

            // Resize Image for category and upload
          $postImage = Image::make($image)->resize(1600,1066)->stream();
          Storage::disk('public')->put('post/'.$imageName,$postImage);
         
   }else{
    $imageName = $post->image;
   }

  
  $post->user_id = Auth::id();
  $post->title = $request->title;
  $post->slug = $slug;
  $post->image = $imageName;
  $post->body = $request->body;
  if (isset($request->status)) {
    $post->status = true;
  }else{
    $post->status = false;
  }
  $post->is_approved = true;
  $post->save();

  $post->categories()->sync($request->categories);
  $post->tags()->sync($request->tags);
  return redirect(route('admin.post.index'))->with('successMsg', 'Post Updated Successfully');
    }


 public function pending(){
  $posts = Post::where('is_approved',false)->get();
  return view('admin.post.pending',compact('posts'));
 }
 

  public function approval($id){
    $post = Post::find($id);
    if ($post->is_approved == false) {
      $post->is_approved = true;
      $post->save();
      $post->user->notify(new AuthorPostApproved($post)); 
      
       $subscribers = Subscriber::all();
     foreach ($subscribers as $subscriber) {
     Notification::route('mail',$subscriber->email)
     ->notify(new NewPostNotify($post));
   }  
  
      return redirect(route('admin.post.pending'))->with('successMsg', 'Post Successfully Approved');
    }else{
      return redirect(route('admin.post.pending'))->with('successMsg', 'Post is Already Approved');
    }

  }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        if (Storage::disk('public')->exists('post/'.$post->image)) {
           Storage::disk('public')->delete('post/'.$post->image);
        }
        $post->categories()->detach();
        $post->tags()->detach();
        $post->delete();
        return redirect(route('admin.post.index'))->with('successMsg', 'Post Deleted Successfully');
    }
}
