# HelPHP JS Libs

HelPHP is a modern PHP toolkit containing everything you (or your AI) need to create any kind of web application:
From a small website running on a LAMP stack, to a large, cloud-clustered, scalable app.

HelPHP embed a set of JavaScript libraries for your UI and controllers, offering a wide range of features, including animations, and are ready made to work with PHP side.

---

# HelPHP JS libs main ideas
First, we have search to create some libs that offering a small fooprint, and that can be use in any browser or webview app style context (android/ios webview, electron, cordova etc... )
IT was tought from MVC perspective, but with pragmatic adaptation.
Javascript has evolved greatly, and our libs are there to complete the main needs and avoid to add multiple framework.

#### Controler yes, modify the "view" yes, not here to create the "model"
On the UI side, if you consider that it's not the job of the controler to build the UI but to control and enrich it, you don't need to redo the job that's already done on the server (backend side).

The backend side will deliver UI parts already in HTML, with correct ID/Names/Classes that will permit to JS to take control easily. 

Of course, in some cases we need to create/add HTML dynamicaly (modal popup, confirm, queries, drag&drop/copy of an element etc...) so those libs offer features for that, but it's not their job to create the model.

#### Global instancing and naming conflict reduction
to permit to access to any object, lib etc the init.js script create the global "h" object.
and will instance some classes that are needed everywhere :
h.a = H_ajax class = h.libs.ajax
h.e = H_events class = h.libs.event
h.v = H_validator class = h.libs.validator
h.modules = array of module instance 

so if you can't access to H_ajax class, (context change, iframe, etc), instead of trying to try a call starting by window. you can use h.a or h.libs.ajax 

H.modules is an array containing the different instance of modules running. 
exemple you have a photo galery with rototing images, if you replicate it the same page, there can issue if the js is not done for that. Yo avoid those cases. All modules scripts are instanciated in h.modules. the array key is module_name+¤+dom_container_id and created automaticaly at module launch.

#### Exposed during dev, obfuscated and compacted for prod :
When you have set devmode = true in main configuration file of your helphp instance, your js are copied dynamicaly into the tmp directory to be loaded separataly and help you debug your script.

In the JS directory you'll find the constants used by the libs. Those constants are created by the PHP side (utils/constants.php of from the maintenance admin module).

In the maintenance admin module, you'll find a button "create minify js and css" or you can use utils/minify.php script to obtain only one js.gz file for you backoffice and for the public side in you instance (jsgz directory). Then swith devmode to false in config/main.php and the js loaded will be only the jsgz one. The speed up is huge, and you'll reduce bandwith and ressource consumption a lot !

it's good practice to keep a dev and prod copy instance, and just copy the gz final files and content from dev to prod. 

#### Thanks to the open source community:
Over the decades, we've found help and solutions from the open source community. Now it's our turn: HelPHP is our contribution, embeding tons of solutions. Sometimes coming from the community, but there is also ours, with some secret tips and tricks that saved our a**** during our career. And all of that is assembled to make a coherent solution.

#### To grow:
We haven't finished converting all our tools, and we have lots of ideas. We hope HelPHP will be useful enough to grow with your needs. And of course, if you can make a little donation to help us, thanks a lot by advance. We hope that HelPHP will become big enough to create a team dedicated 100% of the time to it and keep it open source. All depends the success and your interest for it.
So, for the moment, we'll just focus to polish the V1 and see later... 

---

## Getting started

Please go to our online documentation to start from the good starting point depending your needs.

## Contributing

Please read CONTRIBUTING.md in HelPHP for guidelines on how to contribute to HelPHP.

## Authors and acknowledgment

HelPHP is developed and maintained by:

- Mickaël Bourgeoisat (2009-2025)
- Emile Steiner (2017-2025)
- InRai (2024-2025) 

Special thanks to the open source community for inspiration and solutions.

## License

HelPHP is released under the MIT License

## Project status

HelPHP is under active development.
We are continuously adding features, improving stability, and expanding documentation.
Feedback and contributions are welcome!

## Project support
HelPHP is an open source project supported by InRai association, a group working on R&D for less  comsuming solutions for computing.
