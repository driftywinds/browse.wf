<!DOCTYPE html>
<html>
<head>
	<title>KIM Convo Locator | browse.wf</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="https://browse.wf/Lotus/Interface/Icons/Categories/GrimoireModIcon.png">
</head>
<body data-bs-theme="dark">
	<?php require "components/navbar.php"; ?>
	<div class="container pt-3">
		<div id="loading">
			<p>Loading, please wait...</p>
		</div>
		<div id="content" class="d-none">
			<input id="query" type="text" class="form-control mb-2" placeholder="Enter (part of) a message you want to locate..." />
			<div id="results"></div>
		</div>
	</div>
</body>
<?php require "components/commonjs.html"; ?>
<script>
	if (location.host == "kim.browse.wf" && location.pathname == "/convo-locator.html")
	{
		location.pathname = "/convo-locator";
	}

	const chatroom_to_username = {
		"AoiDialogue_rom.dialogue": "xX GLIMMER Xx",
		"ArthurDialogue_rom.dialogue": "Broadsword",
		"EleanorDialogue_rom.dialogue": "Salem",
		"FlareDialogue_rom.dialogue": "Liminus_Star",
		"HexDialogue_rom.dialogue": "The Hex",
		"JabirDialogue_rom.dialogue": "H16h V0l7463",
		"KayaDialogue_rom.dialogue": "KOLTrial_5115",
		"LettieDialogue_rom.dialogue": "Belladona ~{@",
		//"MinervaDialogue_rom.dialogue": "MomToxicated",
		"MinervaVelemirDialogue_rom.dialogue": "Minerva, Velimir",
		"QuincyDialogue_rom.dialogue": "Soldja1Shot1kil",
		//"VelimirDialogue_rom.dialogue": "PapaPolar",
		"LoidDialogue_rom.dialogue": "POM2_LAB1",
		"LyonDialogue_rom.dialogue": "Lyon",
		"MarieDialogue_rom.dialogue": "Marie",
		"RoatheDialogue_rom.dialogue": "Roathe",
	};

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
			"AoiDialogue_rom.dialogue": { nodes: AoiDialogue_rom, convos: {} },
			"ArthurDialogue_rom.dialogue": { nodes: ArthurDialogue_rom, convos: {} },
			"EleanorDialogue_rom.dialogue": { nodes: EleanorDialogue_rom, convos: {} },
			"FlareDialogue_rom.dialogue": { nodes: FlareDialogue_rom, convos: {} },
			"HexDialogue_rom.dialogue": { nodes: HexDialogue_rom, convos: {} },
			"JabirDialogue_rom.dialogue": { nodes: JabirDialogue_rom, convos: {} },
			"KayaDialogue_rom.dialogue": { nodes: KayaDialogue_rom, convos: {} },
			"LettieDialogue_rom.dialogue": { nodes: LettieDialogue_rom, convos: {} },
			//"MinervaDialogue_rom.dialogue": { nodes: MinervaDialogue_rom, convos: {} },
			"MinervaVelemirDialogue_rom.dialogue": { nodes: MinervaVelemirDialogue_rom, convos: {} },
			"QuincyDialogue_rom.dialogue": { nodes: QuincyDialogue_rom, convos: {} },
			//"VelimirDialogue_rom.dialogue": { nodes: VelimirDialogue_rom, convos: {} },
			"LoidDialogue_rom.dialogue": { nodes: LoidDialogue_rom, convos: {} },
			"LyonDialogue_rom.dialogue": { nodes: LyonDialogue_rom, convos: {} },
			"MarieDialogue_rom.dialogue": { nodes: MarieDialogue_rom, convos: {} },
			"RoatheDialogue_rom.dialogue": { nodes: RoatheDialogue_rom, convos: {} },
		};
		for (const [chatroom_name, chatroom] of Object.entries(chatrooms))
		{
			for (const node of chatroom.nodes)
			{
				if (node.type == "/EE/Types/Engine/StartDialogueNode")
				{
					const convo = node.Content;
					//convo_to_chatroom[convo] = chatroom_name;
					const pending = [ node.Id ];
					const done = [];
					while (pending.length != 0)
					{
						const node_id = pending.pop();
						if (done.indexOf(node_id) != -1)
						{
							continue;
						}
						done.push(node_id);
						const n = chatroom.nodes[node_id];
						if (n.Outgoing)
						{
							for (const choice of n.Outgoing)
							{
								pending.push(choice);
							}
						}
						else if (n.TrueNodes)
						{
							for (const choice of n.TrueNodes)
							{
								pending.push(choice);
							}
							if (n.FalseNodes)
							{
								for (const choice of n.FalseNodes)
								{
									pending.push(choice);
								}
							}
						}
						else if (n.Outputs)
						{
							for (const output of n.Outputs)
							{
								for (const choice of output.Outgoing)
								{
									pending.push(choice);
								}
							}
						}
					}
					chatroom.convos[convo] = done;
				}
			}
		}

		const params = new URLSearchParams(location.hash.toString().replace("#", ""));
		if (params.has("q"))
		{
			document.getElementById("query").value = params.get("q");
			doLocate();
		}

		document.getElementById("loading").classList.add("d-none");
		document.getElementById("content").classList.remove("d-none");
	});

	onLanguageUpdate = function()
	{
		fetch("https://kim.browse.wf/dicts/" + (localStorage.getItem("lang") ?? "en") + ".json").then(res => res.json()).then(dict =>
		{
			window.kim_dict = dict;
			doLocate();
		})
	};

	function node_id_to_convo(chatroom, node_id)
	{
		for (const [convo, convo_node_ids] of Object.entries(chatroom.convos))
		{
			for (const convo_node_id of convo_node_ids)
			{
				if (convo_node_id == node_id)
				{
					return convo;
				}
			}
		}
		console.error("node_id_to_convo failed", chatroom, node_id);
	}

	function normaliseText(text)
	{
		return text.toLowerCase().split("‘").join("'").split("’").join("'").split("\r\n").join(" ");
	}

	function doLocate()
	{
		const lang = localStorage.getItem("lang") || "en";
		const query = normaliseText(document.getElementById("query").value);
		if (query.length >= 3)
		{
			location.hash = "q=" + encodeURIComponent(query);
			document.getElementById("results").innerHTML = "";
			const results_set = {};
			for (const [chatroom_name, chatroom] of Object.entries(chatrooms))
			{
				for (const node of chatroom.nodes)
				{
					let text;
					if (node.type == "/EE/Types/Engine/DialogueNode" || node.type == "/EE/Types/Engine/PlayerChoiceDialogueNode")
					{
						if (node.LocTag ?? node.Content)
						{
							text = kim_dict[node.LocTag] ?? node.LocTag ?? node.Content;
							/*if (node.vars)
							{
								for (const [k, v] of Object.entries(node.vars))
								{
									text = text.split("|"+k+"|").join(kim_dict[v] ?? v);
								}
							}*/
							text = text.split("<RETRO_EMOJI_HEART>").join("<3");
						}
					}
					else if (node.type == "/EE/Types/Engine/CheckBooleanDialogueNode")
					{
						text = "$ Check boolean " + node.Content;
					}
					else if (node.type == "/EE/Types/Engine/CheckBooleanScriptDialogueNode")
					{
						text = "$ Check " + node.Script.Script + ":" + node.Script.Function;
					}
					else if (node.type == "/EE/Types/Engine/SetBooleanDialogueNode")
					{
						text = "$ Boolean " + node.Content + " is now true.";
					}
					else if (node.type == "/EE/Types/Engine/ResetBooleanDialogueNode")
					{
						text = "$ Boolean " + node.Content + " is now false.";
					}
					else if (node.type == "/EE/Types/Engine/CheckMultiBooleanDialogueNode")
					{
						const involvedBooleans = {};
						for (const output of node.Outputs)
						{
							for (const bool of output.Expression.split(", "))
							{
								involvedBooleans[bool] = true;
							}
						}
						delete involvedBooleans["false"];
						text = "$ Evaluate booleans " + Object.keys(involvedBooleans).join(", ");
					}
					else if (node.type == "/EE/Types/Engine/IncCounterDialogueNode")
					{
						const arr = node.Content.split(" ");
						text = "$ Add " + arr[1] + " to " + arr[0] + " counter";
					}
					else if (node.type == "/EE/Types/Engine/CheckCounterDialogueNode")
					{
						text = "$ Evaluate expressions on " + node.CounterName + " counter";
					}
					if (text)
					{
						if (normaliseText(text).indexOf(query) != -1)
						{
							const convo = node_id_to_convo(chatroom, node.Id);

							let sender_name = "Drifter";
							if (node.type == "/EE/Types/Engine/DialogueNode")
							{
								sender_name = chatroom_to_username[chatroom_name];
								if (node.Speaker)
								{
									sender_name = node.Speaker;
									if (kim_dict[sender_name])
									{
										sender_name = kim_dict[sender_name];
									}
								}
							}

							const slug = convo + "+++" + sender_name + "+++" + text;
							if (slug in results_set)
							{
								continue;
							}
							results_set[slug] = true;

							const p = document.createElement("p");
							p.className = "mb-1";
							p.textContent = sender_name + ": " + text + " (" + convo + " • ";
							{
								const a = document.createElement("a");
								a.href = "https://kim.browse.wf/flowcharts_svg/" + lang + "/" + chatroom_name + "/" + convo + ".svg";
								a.target = "_blank";
								a.textContent = "Flowchart";
								p.appendChild(a);
							}
							p.innerHTML += " • ";
							{
								const a = document.createElement("a");
								a.href = "kimulacrum#chatroom=" + chatroom_name + "&dialogue=" + convo;
								a.target = "_blank";
								a.textContent = "Kimulacrum";
								p.appendChild(a);
							}
							p.innerHTML += ")";
							document.getElementById("results").appendChild(p);
						}
					}
				}
			}
		}
		else
		{
			location.hash = "";
			document.getElementById("results").innerHTML = "<p>Please enter at least 3 characters.</p>";
		}
	}
	document.getElementById("query").oninput = doLocate;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://censorcanary.org/censorcanary.js" defer></script>
</html>
