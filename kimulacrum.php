<!DOCTYPE html>
<html>
<head>
	<title>Kimulacrum | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
	<style>
		#history, #choices {
			margin-top: 20px;
		}

		.chat-message {
			margin: 10px 0;
		}

		.chat-message-author, .chat-message-line {
			display: block;
		}

		.choice {
			background: rgb(1, 1, 1, 0.1);
			margin: 7px 0;
			padding: 5px;
			cursor: pointer;
		}

		@media (max-width: 768px) {
			td {
				display: block;
			}
		}

		.chat-message, .choice {
			font-family: "Roboto Condensed", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
		}
	</style>
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container container-fluid pt-3 pb-3">
		<table class="text-center w-100">
			<tr>
				<td>
					<label class="form-label"><b>Chat Controls</b></label><br/>
					<select id="chatroom" class="form-control d-inline-block w-auto" style="min-width:200px">
						<option>SELECT A CHATROOM</option>
						<option value="HexDialogue_rom.dialogue">The Hex</option>
						<option value="ArthurDialogue_rom.dialogue">Broadsword (Arthur)</option>
						<option value="EleanorDialogue_rom.dialogue">Salem (Eleanor)</option>
						<option value="LettieDialogue_rom.dialogue">Belladona ~{@ (Lettie)</option>
						<option value="JabirDialogue_rom.dialogue">H16h V0l7463 (Amir)</option>
						<option value="AoiDialogue_rom.dialogue">xX GLIMMER Xx (Aoi)</option>
						<option value="QuincyDialogue_rom.dialogue">Soldja1Shot1kil (Quincy)</option>
						<option value="FlareDialogue_rom.dialogue">Liminus_Star (Flare)</option>
						<option value="KayaDialogue_rom.dialogue">KOLTrial_5115 (Kaya)</option>
						<option value="MinervaVelemirDialogue_rom.dialogue">Minerva, Velimir</option>
						<!-- <option value="MinervaDialogue_rom.dialogue">MomToxicated (Minerva)</option> -->
						<!-- <option value="VelimirDialogue_rom.dialogue">PapaPolar (Velimir)</option> -->
						<option value="LoidDialogue_rom.dialogue">POM2_LAB1 (Loid)</option>
						<option value="LyonDialogue_rom.dialogue">Lyon</option>
						<option value="MarieDialogue_rom.dialogue">Marie</option>
						<option value="RoatheDialogue_rom.dialogue">Roathe</option>
					</select>
					<select id="dialogue" class="form-control d-inline-block w-auto" style="min-width:200px">
						<option>SELECT A DIALOGUE</option>
					</select>
					<a id="flowchart-link" target="_blank" class="d-none">(Flowchart)</a>
				</td>
				<td>
					<label for="username" class="form-label"><b>Username</b></label>
					<input id="username" class="form-control" value="Drifter" />
				</td>
				<td>
					<label class="form-label"><b>Ambience</b></label><br/>
					<audio id="ambience" controls loop>
						<source src="https://kim.browse.wf/kimulacrum_resources/1999GreatDespairRomanceChatLoop.mp3" type="audio/mpeg" />
					</audio>
				</td>
			</tr>
		</table>

		<div id="history"></div>
		<div id="choices"></div>
	</div>
</body>
<?php require "components/commonjs.html"; ?>
<script>
	if (location.host == "kim.browse.wf" && location.pathname == "/kimulacrum.html")
	{
		location.pathname = "/kimulacrum";
	}

	window.chatroom = undefined;
	window.dialogue = undefined;

	const retro_emoji_paths = [
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiAnnoyed1_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiAnnoyed2_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiAoiCat_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiAwHug_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiCat_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiCheeky_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiCoolArmsUp_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiCow_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiDog_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiFlowers_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiFood_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiGiveLove_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiGlare_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHappy1_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHappy2_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHappyArmsUp_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHappyHand_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHeart_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHollars_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiHorrified_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiJoy_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiKnife_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiMusic1_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiMusic2_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiOhno_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiPassGlasses_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiPumpedUp_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiShrug_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiSleeping_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiSmitten_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiSneaky_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiStare_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiSunglasses_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiSweat_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiTM_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiThumbsUp_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiTinySmile_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiTwoHugs_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiWhoa_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiWink_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiWut_d.png",
		"/Lotus/Interface/Graphics/Retro/Texts/Emoji/RetroEmojiYum_d.png",
	];

	function resolveEmojis(text)
	{
		return text.replaceAll(/<[^>]+>/g, (match) => {
			const name = match.split("<").join("").split(">").join("");
			const simpleName = name.toLowerCase().split("_").join("");
			const emojiPath = retro_emoji_paths.find(emojiPath => emojiPath.toLowerCase().indexOf(simpleName) !== -1);
			if (emojiPath)
			{
				return "<img alt='<" + name + ">' style='height:1em;position:relative;bottom:-3px' src='https://browse.wf" + emojiPath + "' />";
			}
			return "&lt;" + name + "&gt;";
		});
	}

	function clearChat()
	{
		document.getElementById("history").innerHTML = "";
		document.getElementById("choices").innerHTML = "";
		window.choice_history = [];
	}

	function addToHistory(author, text, chemistry, transmission)
	{
		const div = document.createElement("div");
		div.className = "chat-message";
		{
			const span = document.createElement("span");
			span.classList.add("chat-message-author");
			if (author == "Drifter")
			{
				span.classList.add("chat-message-author-self");
				span.textContent = document.getElementById("username").value || "Drifter";
				span.style.color = "red";
			}
			else if (author == "System")
			{
				span.textContent = author;
				span.style.color = "orange";
			}
			else
			{
				span.textContent = author;
				span.style.color = "blue";
			}
			div.appendChild(span);
		}
		{
			const span = document.createElement(chemistry ? "b" : "span");
			span.innerHTML = resolveEmojis(text.trim() == "" ? "&#8203;" : text).split("\r\n").join("<br/>");
			div.appendChild(span);
		}
		if (chemistry)
		{
			const span = document.createElement("span");
			span.textContent += " (+" + chemistry + " Chemistry)";
			div.appendChild(span);
		}
		if (transmission)
		{
			const audio = document.createElement("audio");
			audio.setAttribute("controls", "controls");
			{
				const source = document.createElement("source");
				source.setAttribute("src", `https://kim.browse.wf/kimulacrum_resources/RoatheMemoryLogs/${transmission.split("/").pop()}_en.ogg`);
				source.setAttribute("type", "audio/ogg");
				audio.appendChild(source);
			}
			audio.style.display = "block";
			div.appendChild(audio);
		}
		document.getElementById("history").appendChild(div);
	}

	function presentChoice(text, callback)
	{
		const choiceId = document.getElementById("choices").children.length;
		const div = document.createElement("div");
		div.className = "choice";
		div.innerHTML = "• " + resolveEmojis(text);
		div.onclick = function()
		{
			choice_history.push(choiceId);

			if (!window.replayed_choice)
			{
				saveState();
			}
			window.replayed_choice = false;

			callback();
		};
		document.getElementById("choices").appendChild(div);
	}

	function endChoices()
	{
		if (window.replay_choices?.length)
		{
			const choiceId = window.replay_choices.shift();
			window.replayed_choice = true;
			document.getElementById("choices").children[choiceId].onclick();
		}
	}

	function getNodeText(node)
	{
		let text = kim_dict[node.LocTag] ?? node.LocTag ?? node.Content;
		/*if (node.vars)
		{
			for (const [k, v] of Object.entries(node.vars))
			{
				text = text.split("|"+k+"|").join(kim_dict[v] ?? v);
			}
		}*/
		return text;
	}

	function processNode(node, chemistry)
	{
		//console.log("processNode", node);

		if (node.type == "/EE/Types/Engine/DialogueNode")
		{
			let sender_name = window.sender_name;
			if (node.Speaker)
			{
				sender_name = node.Speaker;
				if (kim_dict[sender_name])
				{
					sender_name = kim_dict[sender_name];
				}
			}
			addToHistory(sender_name, getNodeText(node), chemistry, node.Transmission);
			chemistry = undefined;
		}
		else if (node.type == "/EE/Types/Engine/PlayerChoiceDialogueNode")
		{
			addToHistory("Drifter", getNodeText(node));
		}
		else if (node.type == "/EE/Types/Engine/ChemistryDialogueNode")
		{
			chemistry = node.ChemistryDelta;
		}
		else if (node.type == "/EE/Types/Engine/EndDialogueNode")
		{
			let msg = "Chat finished.";
			if (chemistry) // #chatroom=AoiDialogue_rom.dialogue&dialogue=AoiRank5Convo6&choices=0,0,0,0,1,1,1,1
			{
				msg += " +" + chemistry + " Chemistry.";
			}
			if (node.Content)
			{
				msg += " The next chat after this would be " + node.Content + ".";
			}
			addToHistory("System", msg);
		}
		else if (node.type == "/EE/Types/Engine/CheckBooleanDialogueNode")
		{
			document.getElementById("choices").innerHTML = "";
			presentChoice("$ Boolean " + node.Content + " is true", function()
			{
				processChoices(node.TrueNodes ?? []);
			});
			presentChoice("$ Boolean " + node.Content + " is false", function()
			{
				processChoices(node.FalseNodes ?? []);
			});
			endChoices();
			return;
		}
		else if (node.type == "/EE/Types/Engine/CheckBooleanScriptDialogueNode")
		{
			document.getElementById("choices").innerHTML = "";
			presentChoice("$ " + node.Script.Script + ":" + node.Script.Function + " evaluates to true", function()
			{
				processChoices(node.TrueNodes ?? []);
			});
			presentChoice("$ " + node.Script.Script + ":" + node.Script.Function + " evaluates to false", function()
			{
				processChoices(node.FalseNodes ?? []);
			});
			endChoices();
			return;
		}
		else if (node.type == "/EE/Types/Engine/CheckMultiBooleanDialogueNode")
		{
			document.getElementById("choices").innerHTML = "";
			for (const output of node.Outputs)
			{
				presentChoice("$ " + output.Expression.split(", ").join(" & "), function()
				{
					processChoices(output.Outgoing);
				});
			}
			endChoices();
			return;
		}
		else if (node.type == "/EE/Types/Engine/SetBooleanDialogueNode")
		{
			addToHistory("System", "Boolean " + node.Content + " is now true.");
		}
		else if (node.type == "/EE/Types/Engine/ResetBooleanDialogueNode")
		{
			addToHistory("System", "Boolean " + node.Content + " is now false.");
		}
		else if (node.type == "/EE/Types/Engine/SpecialCompletionDialogueNode")
		{
			if (node.OtherDialogueInfos?.length == 1)
			{
				addToHistory("System", node.OtherDialogueInfos[0].Tag + " would now be queued in " + node.OtherDialogueInfos[0].Dialogue + ".");
			}
			else
			{
				console.warn("Unhandled node:", node);				
			}
		}
		else if (node.type == "/EE/Types/Engine/IncCounterDialogueNode")
		{
			const arr = node.Content.split(" ");
			addToHistory("System", "Add " + arr[1] + " to " + arr[0] + " counter.");
		}
		else if (node.type == "/EE/Types/Engine/CheckCounterDialogueNode")
		{
			document.getElementById("choices").innerHTML = "";
			for (const output of node.Outputs)
			{
				presentChoice("$ " + node.CounterName + " counter: " + output.Expression, function()
				{
					processChoices(output.Outgoing ?? []);
				});
			}
			endChoices();
			return;
		}
		else if (node.type != "/EE/Types/Engine/StartDialogueNode")
		{
			console.warn("Unhandled node:", node);
		}

		processChoices(node.Outgoing ?? [], chemistry);
	}

	function processChoices(choices, chemistry)
	{
		document.getElementById("choices").innerHTML = "";
		if (choices.length == 1)
		{
			processNode(nodes[choices[0]], chemistry);
		}
		else
		{
			choices.forEach(choiceNodeId => {
				const choiceNode = nodes[choiceNodeId];
				presentChoice(getNodeText(choiceNode), function()
				{
					processNode(choiceNode);
				});
			});
			endChoices();
		}
	}

	function saveState()
	{
		let hash = "";
		if (window.chatroom)
		{
			hash += "chatroom=" + window.chatroom;
			if (window.dialogue)
			{
				hash += "&dialogue=" + window.dialogue;
				if (window.choice_history && window.choice_history.length)
				{
					hash += "&choices=" + window.choice_history.join(",");
				}
			}
		}
		location.hash = hash;
	}

	function stringToSortInt(str, max_len)
	{
		let score = 0n;
		let shift = BigInt(max_len * 8);
		const addByteToScore = function(b)
		{
			if (b < 0 || b > 0xFF)
			{
				throw new Error(`Can't add byte ${b} to score`);
			}
			score |= BigInt(b) << shift;
			if (shift <= 0n)
			{
				throw new Error(`String too long: ${str}`);
			}
			shift -= 8n;
		};
		let num;
		for (let i = 0; i != str.length; ++i)
		{
			const cc = str.charCodeAt(i);
			if (cc >= 48 && cc <= 57)
			{
				num ??= 0;
				num *= 10;
				num += cc - 48;
			}
			else
			{
				if (num !== undefined)
				{
					addByteToScore(num);
					num = undefined;
				}
				addByteToScore(cc);
			}
		}
		if (num !== undefined)
		{
			addByteToScore(num);
		}
		return score;
	}

	function setActiveChatroom(chatroom)
	{
		if (window.chatroom != chatroom)
		{
			window.chatroom = chatroom;
			window.sender_name = document.querySelector("option[value='"+chatroom+"']").textContent.split(" (")[0];

			const audioSource = 
				(chatroom == "LoidDialogue_rom.dialogue" || chatroom == "LyonDialogue_rom.dialogue" || chatroom == "MarieDialogue_rom.dialogue" || chatroom == "RoatheDialogue_rom.dialogue")
				? "https://kim.browse.wf/kimulacrum_resources/TauTriadRomanceChatLoop.mp3"
				: "https://kim.browse.wf/kimulacrum_resources/1999GreatDespairRomanceChatLoop.mp3"
				;
			if (document.querySelector("#ambience source").getAttribute("src") != audioSource)
			{
				const playing = !document.querySelector("#ambience").paused;
				document.querySelector("#ambience source").setAttribute("src", audioSource);
				document.querySelector("#ambience").load();
				if (playing)
				{
					document.querySelector("#ambience").play();
				}
			}

			window.nodes = window.chatrooms[chatroom];

			window.dialogue_name_to_start_node_id = {};
			for (const node of nodes)
			{
				if (node.type == "/EE/Types/Engine/StartDialogueNode")
				{
					dialogue_name_to_start_node_id[node.Content] = node.Id;
				}
			}
			dialogue_name_to_start_node_id = Object.keys(dialogue_name_to_start_node_id).sort((a, b) => {
				const max_len = Math.max(a.length, b.length);
				const aScore = stringToSortInt(a, max_len);
				const bScore = stringToSortInt(b, max_len);
				return aScore < bScore ? -1 : +1;
			}).reduce((obj, key) => { 
				obj[key] = dialogue_name_to_start_node_id[key]; 
				return obj;
			}, {});

			clearChat();
			for (const name of Object.keys(dialogue_name_to_start_node_id))
			{
				const option = document.createElement("option");
				option.textContent = name;
				document.getElementById("dialogue").appendChild(option);
			}
		}
	}

	function setActiveDialogue(dialogue)
	{
		document.getElementById("flowchart-link").classList.remove("d-none");
		document.getElementById("flowchart-link").href = "https://kim.browse.wf/flowcharts_svg/" + (localStorage.getItem("lang") ?? "en") + "/" + chatroom + "/" + dialogue + ".svg";
		window.dialogue = dialogue;
		clearChat();
		processNode(nodes[dialogue_name_to_start_node_id[dialogue]]);
	}

	function recreateChat()
	{
		if (window.dialogue)
		{
			window.replay_choices = window.choice_history;
			setActiveDialogue(window.dialogue);
		}
	}

	addToHistory("System", "Loading data...");
	Promise.all([
		fetch("https://kim.browse.wf/data/AoiDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/ArthurDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/EleanorDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/FlareDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/HexDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/JabirDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/KayaDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/LettieDialogue_rom.dialogue.json").then(res => res.json()),
		//fetch("https://kim.browse.wf/data/MinervaDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/MinervaVelemirDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/QuincyDialogue_rom.dialogue.json").then(res => res.json()),
		//fetch("https://kim.browse.wf/data/VelimirDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/LoidDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/LyonDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/MarieDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/data/RoatheDialogue_rom.dialogue.json").then(res => res.json()),
		fetch("https://kim.browse.wf/dicts/" + (localStorage.getItem("lang") ?? "en") + ".json").then(res => res.json())
	]).then(([
		AoiDialogue_rom,
		ArthurDialogue_rom,
		EleanorDialogue_rom,
		FlareDialogue_rom,
		HexDialogue_rom,
		JabirDialogue_rom,
		KayaDialogue_rom,
		LettieDialogue_rom,
		//MinervaDialogue_rom,
		MinervaVelemirDialogue_rom,
		QuincyDialogue_rom,
		//VelimirDialogue_rom,
		LoidDialogue_rom,
		LyonDialogue_rom,
		MarieDialogue_rom,
		RoatheDialogue_rom,
		dict
	]) => {
		window.kim_dict = dict;
		window.chatrooms = {
			"AoiDialogue_rom.dialogue": AoiDialogue_rom,
			"ArthurDialogue_rom.dialogue": ArthurDialogue_rom,
			"EleanorDialogue_rom.dialogue": EleanorDialogue_rom,
			"FlareDialogue_rom.dialogue": FlareDialogue_rom,
			"HexDialogue_rom.dialogue": HexDialogue_rom,
			"JabirDialogue_rom.dialogue": JabirDialogue_rom,
			"KayaDialogue_rom.dialogue": KayaDialogue_rom,
			"LettieDialogue_rom.dialogue": LettieDialogue_rom,
			//"MinervaDialogue_rom.dialogue": MinervaDialogue_rom,
			"MinervaVelemirDialogue_rom.dialogue": MinervaVelemirDialogue_rom,
			"QuincyDialogue_rom.dialogue": QuincyDialogue_rom,
			//"VelimirDialogue_rom.dialogue": VelimirDialogue_rom,
			"LoidDialogue_rom.dialogue": LoidDialogue_rom,
			"LyonDialogue_rom.dialogue": LyonDialogue_rom,
			"MarieDialogue_rom.dialogue": MarieDialogue_rom,
			"RoatheDialogue_rom.dialogue": RoatheDialogue_rom,
		};

		clearChat();

		document.getElementById("chatroom").onchange = function(event)
		{
			document.getElementById("dialogue").innerHTML = "<option>SELECT A DIALOGUE</option>";
			clearChat();

			window.chatroom = undefined;
			window.dialogue = undefined;
			if (this.value != "SELECT A CHATROOM")
			{
				setActiveChatroom(this.value);
			}
			else
			{
				document.getElementById("flowchart-link").classList.add("d-none");
			}
			if (event)
			{
				saveState();
			}
		};
		if (document.getElementById("chatroom").value != "SELECT A CHATROOM")
		{
			document.getElementById("chatroom").onchange();
		}

		window.onhashchange = function()
		{
			const params = new URLSearchParams(location.hash.toString().replace("#", ""));
			if (params.has("chatroom"))
			{
				document.getElementById("chatroom").value = params.get("chatroom");
				setActiveChatroom(params.get("chatroom"));
				if (params.has("dialogue"))
				{
					document.getElementById("dialogue").value = params.get("dialogue");
					if (window.dialogue != params.get("dialogue"))
					{
						setActiveDialogue(params.get("dialogue"));
					}
					if (window.choice_history.join(",") != (params.get("choices") || ""))
					{
						window.replay_choices = (params.get("choices") ? params.get("choices").split(",").map(x => parseInt(x)) : []);
						setActiveDialogue(params.get("dialogue"));
					}
				}
				else
				{
					document.getElementById("flowchart-link").classList.add("d-none");
				}
			}
			else
			{
				document.getElementById("flowchart-link").classList.add("d-none");
			}
		};
		window.onhashchange();

		// Got important data, so network is free to preload emojis now.
		for (const path of retro_emoji_paths)
		{
			new Image().src = "https://browse.wf" + path;
		}
	});

	document.getElementById("dialogue").onchange = function()
	{
		window.dialogue = undefined;
		if (this.value != "SELECT A DIALOGUE")
		{
			setActiveDialogue(this.value);
		}
		else
		{
			document.getElementById("flowchart-link").classList.add("d-none");
		}
		saveState();
	};

	onLanguageUpdate = function()
	{
		fetch("https://kim.browse.wf/dicts/" + (localStorage.getItem("lang") ?? "en") + ".json").then(res => res.json()).then(dict =>
		{
			window.kim_dict = dict;
			recreateChat();
		})
	};

	document.getElementById("username").oninput = function()
	{
		const username = this.value || "Drifter";
		document.querySelectorAll(".chat-message-author-self").forEach(elm => elm.textContent = username);
	};
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://censorcanary.org/censorcanary.js" defer></script>
</html>
