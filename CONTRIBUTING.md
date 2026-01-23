# Contributing to helPHP
Here comes the classic but essential contributing.md ! 
Thanks for taking the time to read this contribute! ❤️

As we've already said, HelPHP is our huge kiss to the open source community !
The community help us, and we want to help the community !
So we offer our best tools as open source, and we hope you can help us to enhance them.

All types of contributions are encouraged and valued. See the [Table of Contents](#table-of-contents) for different ways to help and details about how this project handles them. Please make sure to read the relevant section before making your contribution. It will make it a lot easier for us maintainers and smooth out the experience for all involved.

And if you like the project, but just don't have time to contribute, that's fine. There are other easy ways to support the project and show your appreciation, which we would also be very happy about:
> - Star the project
> - Share about it on your social network
> - Refer this project in your project's readme
> - Mention the project at local meetups and tell your friends/colleagues
> - Make a little donation to support us


## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [I Have a Question](#i-have-a-question)
- [I Want To Contribute](#i-want-to-contribute)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)
- [Your First Code Contribution](#your-first-code-contribution)
- [Improving The Documentation](#improving-the-documentation)
- [Join The Project Team](#join-the-project-team)
- [Donate](#donate)


## Code of Conduct

This project and everyone participating in it is governed by the common sens ;) 
Please be polite, respectfull, and by participating, you are expected to uphold this code. 
Please report unacceptable behavior to <contact@inrai.fr>.
As we are actually based in France, we have to respect French and U.E laws about illegal content.
HelPHP contain very few piece of code coming from other source, you can check their licence in libs/externals and js/externals.

## I Have a Question

> If you want to ask a question, we assume that you have read the available [docs.helphp.org]().

Before you ask a question, it is best to search for existing [Issues](/issues) that might help you. In case you have found a suitable issue and still need clarification, you can write your question in this issue. It is also advisable to search the internet for answers first.

If you then still feel the need to ask a question and need clarification, we recommend the following:

- Open an [Issue](/issues/new) with label "question".
- Provide as much context as you can about what you're running into.
- Provide project and platform versions, depending on what seems relevant.

We will then take care of the issue as soon as possible.

If you have a non technical question about helPHP (who are the geniuses who made this ? it's for time magazine ! ... Have you got a job for me ? i've made 1245 contrib on helphp !), please send it to <contact@inrai.fr>

## I Want To Contribute

> ### Legal Notice 
> When contributing to this project, you must agree that you have authored 100% of the content, that you have the necessary rights to the content and that the content you contribute may be provided under the project license.

### Before Submitting a Bug Report
HelPHP is a big creature, and sometimes she had to blow her noise ...

If you think have a found a critical security issue, don't open immediatly a new issue, send us a mail before at <contact@inrai.fr> !

A good bug report shouldn't leave others needing to chase you up for more information. 
Therefore, we ask you to investigate carefully, collect information and describe the issue in detail in your report. 
Please complete the following steps in advance to help us fix any potential bug as fast as possible.

- Make sure that you are using the latest version.
- Determine if your bug is really a bug and not an error on your side e.g. using incompatible environment components/versions (Make sure that you have read the [docs.helphp.org](). If you are looking for support, you might want to check [this section](#i-have-a-question)).
- To see if other users have experienced (and potentially already solved) the same issue you are having, check if there is not already a bug report existing for your bug or error in the [bug tracker](issues?q=label%3Abug).
- Also make sure to search the internet (including Stack Overflow) to see if users outside of the GitHub community have discussed the issue.
- Collect information about the bug:
- helphp log, apache error log (please not all the file, just the corresponding part)
- OS, Platform and Version (Windows, Linux, macOS, x86, ARM)
- Version of PHP, HTTP server, Navigator,etc, depending on what seems relevant.
- Possibly your input and the output
- Can you reliably reproduce the issue? And can you also reproduce it with older versions?

#### Reporting Bugs

If you think have a found a critical security issue, don't open immediatly a new issue, send us a mail before at <contact@inrai.fr> !

- Open an [Issue](/issues/new) with a corresponding label depending the gravity of the disease: 
        - `small issue` (typos, UI not perfect, something that doesn't break the monster)
        - `medium issue` (annoying, the creature still continue to walk, but in ugly manner !)
        - `critical issue` (the beast is dead ! or will die soon if we don't fix it)
- Please select a good title for your issue (to make it easy to find later)
- Provide as much context as you can about what you're running into.
- Provide project and platform versions, depending on what seems relevant.

#### After the report ?

- The project team will label the issue accordingly.
- A team member will try to reproduce the issue with your provided steps.
 If there are no reproduction steps or no obvious way to reproduce the issue, the team will ask you for those steps and mark the issue as `needs-repro`. 
 Bugs with the `needs-repro` tag will not be addressed until they are reproduced.
- If the team is able to reproduce the issue, it will be marked `needs-fix`, as well as possibly other tags (such as `critical issue`), and the issue will be left to be [implemented by someone](#your-first-code-contribution).
- if the issue has been already fixed, it will be closed with the fixed issue number or if it's fixed thru upgrade ("fixed in las version").
- If the issue is an error on your side, and you correct it with existing documentation, it will just be closed with mention "not an issue, please read the documentation".


### Suggesting Enhancements

Open an [Issue](/issues/new) with the label `Suggestion`. 
Please indicate what motivate your suggestion, and what it will bring to the project and community.
Suggestion like "please recode everything to be compliant with norme PSR89² because it's easier to read for me" will be ignored.
HelPHP have been rewrited and redesigned already 7 times before becoming the actual V1, it was forged by experience and not by certainties or conventions.
So we'll really take time to read all suggestion and discuss it, but it must be a real advance to be adopted.

Soon we'll also add a page for "enhancement suggestion" and vote formular on helphp.org, to add all good suggestion proposed, and let the community vote for them to indicate the priority. 

Some adtionnal components are also already in construction... 

#### Before Submitting an Enhancement

- Make sure that you are using the latest version.
- Read the [documentation]() carefully and find out if the functionality is already covered, maybe by an individual configuration.
- Perform a [search](/issues) to see if the enhancement has already been suggested. If it has, add a comment to the existing issue instead of opening a new one.
- Find out whether your idea fits with the scope and aims of the project. It's up to you to make a strong case to convince the project's developers of the merits of this feature. Keep in mind that we want features that will be useful to the majority of our users and not just a small subset. If you're just targeting a minority of users, consider writing an add-on/plugin library.


### Your First Contribution

- For "big" code contribution, please fork the project (branch develop), and be careful to keep it up to date (merge all commit to your branch).
When ready, send your pull request and if possible with a link where to test the result. 
Don't hesitate to ask us to check if  your contribution is a good idea thru an issue with label "suggestion" before doing it to not lose your time.

### Improving The Documentation
- For documentation or small code contribution, you can use also an issue with "suggestion label" and avoid a huge fork process etc...
As the core team is originating from France, the english version is surely not perfect, so don't hesitate to indicate when some sentence is not correct or difficult to understand (we're not pro translators, and IA translating is far from perfect ;) (so we write most of it by hand !) ...

## Join The Project Team

It's simple, send us a mail to <contact@inrai.fr>, and depending your contribution and merit, we'll acknowlege you or not.
Of course, if you'd just work on the same small piece of code, it will be hard for you to participate with the main team as it's necessary to be able to envision all HelPHP to discuss about it and make strategic choice.  So take your time and make your proposal when you think that you've mastered the beast.

If HelPHP become a huge success, perhaps our association (InRai) will need to offer some jobs in the future. Of course we'll make job offers to best contributors.

## Donate to help us

On [helphp.org](https://www.helphp.org) homepage, you'll find a donate button. 
With it, you can make a oneshot or recurring donation to support our work.
Of course, like any team, we need some money to pay the different services (servers/domains/electricity) and when their is enough money, we can hire some help to speed up on the current WIP. 
So if you want to help up of just offer to us a little coffee, thanks in advance :)


